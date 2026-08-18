# Instalação do Assinatura PDF no Ubuntu Server

Este guia prepara um Ubuntu Server 24.04 LTS com Apache, PHP 8.5, Composer,
QPDF e os certificados necessários para executar este projeto.

> Os comandos que começam com `sudo` precisam ser executados por um usuário
> com permissão administrativa. Troque `assinatura.exemplo.com` pelo domínio
> real e ajuste o diretório do projeto se necessário.

## 1. Atualizar o servidor

```bash
sudo apt update
sudo apt upgrade -y
sudo apt install -y software-properties-common ca-certificates curl unzip git openssl
```

Opcionalmente, configure o fuso horário:

```bash
sudo timedatectl set-timezone America/Sao_Paulo
timedatectl
```

## 2. Instalar Apache e PHP 8.5

O Ubuntu 24.04 não entrega o PHP 8.5 em seu repositório padrão. Para reproduzir
a versão usada neste projeto, adicione o PPA mantido por Ondřej Surý:

```bash
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update
sudo apt install -y apache2 libapache2-mod-php8.5 \
  php8.5-cli php8.5-common php8.5-curl php8.5-gd php8.5-mbstring \
  php8.5-opcache php8.5-xml php8.5-zip
sudo a2enmod php8.5
sudo systemctl enable --now apache2
sudo systemctl restart apache2
```

Confirme as versões e extensões importantes:

```bash
php -v
php -m | grep -E 'curl|fileinfo|gd|mbstring|openssl|xml|zip|zlib'
apache2ctl -v
```

## 3. Instalar QPDF

O parser gratuito do FPDI não abre alguns PDFs com xref/object streams
comprimidos. O projeto usa QPDF para normalizá-los automaticamente:

```bash
sudo apt install -y qpdf
qpdf --version
```

## 4. Instalar o Composer

Use o instalador oficial e valide sua assinatura antes da execução:

```bash
cd /tmp
EXPECTED_CHECKSUM="$(curl -fsSL https://composer.github.io/installer.sig)"
curl -fsSLo composer-setup.php https://getcomposer.org/installer
ACTUAL_CHECKSUM="$(php -r "echo hash_file('sha384', 'composer-setup.php');")"
test "$EXPECTED_CHECKSUM" = "$ACTUAL_CHECKSUM"
sudo php composer-setup.php --install-dir=/usr/local/bin --filename=composer
rm composer-setup.php
composer --version
```

Se o comando `test` retornar erro, não execute o instalador: baixe-o novamente
e confira as instruções atuais em <https://getcomposer.org/download/>.

## 5. Copiar e instalar o projeto

Exemplo usando `/var/www/assinaturapdf_preview`:

```bash
sudo mkdir -p /var/www/assinaturapdf_preview
sudo chown -R "$USER":www-data /var/www/assinaturapdf_preview
cd /var/www/assinaturapdf_preview
```

Copie os arquivos do projeto para esse diretório. Se o código estiver em um
repositório Git, use o endereço real dele:

```bash
git clone URL_DO_REPOSITORIO /var/www/assinaturapdf_preview
cd /var/www/assinaturapdf_preview
composer install --no-dev --prefer-dist --optimize-autoloader
```

Não execute `composer update` na instalação de produção. `composer install`
respeita exatamente as versões registradas no `composer.lock`.

Crie os diretórios graváveis e aplique permissões:

```bash
cd /var/www/assinaturapdf_preview
mkdir -p uploads output certificados
sudo chown -R www-data:www-data uploads output certificados
sudo chmod 750 uploads output certificados
sudo find uploads output -type f -exec chmod 640 {} \;
sudo find certificados -type f -exec chmod 640 {} \;
sudo find . -type d -not -path './uploads*' -not -path './output*' \
  -not -path './certificados*' -exec chmod 755 {} \;
sudo find . -type f -not -path './uploads/*' -not -path './output/*' \
  -not -path './certificados/*' -exec chmod 644 {} \;
```

O Apache precisa escrever somente em `uploads`, `output` e, durante a
instalação, receber acesso de leitura aos certificados.

## 6. Incluir o certificado de assinatura digital

O certificado usado para assinar PDFs não é o mesmo certificado HTTPS do site.
O código espera estes arquivos:

```text
certificados/certificate.crt
certificados/private.key
```

### Opção A: extrair de um arquivo PFX/P12

Copie `certificado.pfx` para a pasta `certificados` e execute:

```bash
cd /var/www/assinaturapdf_preview/certificados
openssl pkcs12 -in certificado.pfx -clcerts -nokeys -out certificate.crt
openssl pkcs12 -in certificado.pfx -nocerts -out private.key
sudo chown www-data:www-data certificate.crt private.key
sudo chmod 640 certificate.crt private.key
```

O segundo comando solicitará uma senha para proteger a chave privada. Essa senha
deve coincidir com o terceiro argumento de `setSignature()` em `processar.php`.
Em produção, prefira carregar a senha de uma variável de ambiente ou de um cofre
de segredos em vez de mantê-la escrita no código.

Confira se certificado e chave formam um par, sem exibir a chave:

```bash
openssl x509 -in certificate.crt -pubkey -noout | openssl sha256
openssl pkey -in private.key -pubout | openssl sha256
```

Os dois hashes precisam ser iguais.

### Opção B: certificado de teste autoassinado

Use somente em desenvolvimento. Leitores de PDF não confiarão automaticamente
em um certificado autoassinado:

```bash
cd /var/www/assinaturapdf_preview/certificados
openssl req -x509 -newkey rsa:3072 -sha256 -days 365 \
  -keyout private.key -out certificate.crt \
  -subj '/C=BR/O=Ambiente de Teste/CN=Assinatura PDF'
sudo chown www-data:www-data certificate.crt private.key
sudo chmod 640 certificate.crt private.key
```

Para assinatura com validade jurídica e cadeia de confiança, use um certificado
emitido por autoridade certificadora compatível com os requisitos da organização
(por exemplo, ICP-Brasil quando aplicável).

Nunca publique `private.key`, arquivos `.pfx/.p12` ou suas senhas no Git.

## 7. Configurar limites do PHP

Crie uma configuração específica para uploads. O valor do POST deve ser maior
que o limite do arquivo:

```bash
sudo tee /etc/php/8.5/apache2/conf.d/99-assinaturapdf.ini >/dev/null <<'EOF'
upload_max_filesize = 25M
post_max_size = 30M
memory_limit = 256M
max_execution_time = 120
display_errors = Off
log_errors = On
EOF
sudo systemctl restart apache2
```

Verifique a configuração carregada pela linha de comando (a configuração do
Apache fica em outro diretório, mas os valores ajudam no diagnóstico):

```bash
php --ini
sudo apache2ctl configtest
```

## 8. Criar o VirtualHost do Apache

```bash
sudo tee /etc/apache2/sites-available/assinaturapdf.conf >/dev/null <<'EOF'
<VirtualHost *:80>
    ServerName assinatura.exemplo.com
    DocumentRoot /var/www/assinaturapdf_preview

    <Directory /var/www/assinaturapdf_preview>
        Options -Indexes
        AllowOverride None
        Require all granted
    </Directory>

    # A chave privada e as dependências nunca podem ser servidas pela web.
    <Directory /var/www/assinaturapdf_preview/certificados>
        Require all denied
    </Directory>

    <Directory /var/www/assinaturapdf_preview/vendor>
        Require all denied
    </Directory>

    <Directory /var/www/assinaturapdf_preview/output>
        Require all denied
    </Directory>

    # PDFs enviados precisam ser lidos pelo PDF.js, mas nunca executados.
    <Directory /var/www/assinaturapdf_preview/uploads>
        Options -ExecCGI -Indexes
        php_admin_flag engine off
        Require all granted
    </Directory>

    <Directory /var/www/assinaturapdf_preview/.git>
        Require all denied
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/assinaturapdf-error.log
    CustomLog ${APACHE_LOG_DIR}/assinaturapdf-access.log combined
</VirtualHost>
EOF

sudo a2ensite assinaturapdf.conf
sudo a2dissite 000-default.conf
sudo apache2ctl configtest
sudo systemctl reload apache2
```

Aponte o DNS de `assinatura.exemplo.com` para o IP do servidor. Para um teste
local, acesse o IP diretamente ou adicione o domínio ao arquivo `hosts` do seu
computador.

## 9. Ativar HTTPS com Let's Encrypt

Esta etapa exige um domínio público já apontando para o servidor:

```bash
sudo apt install -y certbot python3-certbot-apache
sudo certbot --apache -d assinatura.exemplo.com
sudo certbot renew --dry-run
```

Libere somente os serviços necessários no firewall:

```bash
sudo ufw allow OpenSSH
sudo ufw allow 'Apache Full'
sudo ufw enable
sudo ufw status
```

## 10. Testar a instalação

```bash
cd /var/www/assinaturapdf_preview
composer check-platform-reqs --no-dev
sudo -u www-data test -r certificados/certificate.crt
sudo -u www-data test -r certificados/private.key
sudo -u www-data test -w uploads
sudo -u www-data test -w output
curl -I http://assinatura.exemplo.com/
```

Abra o site, envie um PDF, selecione a posição da marca visual e clique em
**Processar PDF**. Teste tanto um PDF simples quanto um PDF que use compressão.

Logs úteis durante o diagnóstico:

```bash
sudo tail -f /var/log/apache2/assinaturapdf-error.log
sudo journalctl -u apache2 -f
```

Se aparecer novamente a mensagem de compressão não suportada pelo FPDI, confirme:

```bash
command -v qpdf
sudo -u www-data /usr/bin/qpdf --version
```

## 11. Manutenção e segurança

- Faça backup seguro do certificado e da chave privada.
- Restrinja o tamanho e o tipo dos uploads e remova arquivos antigos de
  `uploads` e `output` periodicamente.
- Atualize o Ubuntu e reinstale dependências PHP regularmente:

```bash
sudo apt update && sudo apt upgrade -y
cd /var/www/assinaturapdf_preview
composer audit
composer install --no-dev --prefer-dist --optimize-autoloader
```

- Antes de trocar certificados, teste a nova chave e mantenha uma cópia segura
  do certificado anterior conforme a política de retenção da organização.

## Referências

- PPA PHP: <https://launchpad.net/~ondrej/+archive/ubuntu/php>
- Composer: <https://getcomposer.org/download/>
- Apache no Ubuntu: <https://documentation.ubuntu.com/server/how-to/web-services/install-apache2/>
- Certificados HTTPS no Ubuntu: <https://ubuntu.com/server/docs/how-to/security/obtain-tls-certificates/>
