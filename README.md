# assinaturapdf
projeto em php para assinar arquivos pdf com certificado


## Comandos do composer
```
 composer require tecnickcom/tcpdf
 composer require setasign/fpdi
```

## Estrutura de pastas necessárias
```
/index.php
/processar.php
/uploads/
/assinados/
/certificados/
/vendor/
```

## Extensões necessárias

- extension=openssl
- extension=fileinfo
- extension=mbstring
- extension=gd

### Versão do PHP utilizada
> Foi utilizada a versão 8.5 mas é possível utilizar outras versões compatíveis. Neste caso exige-se que as extensões instaladas abaixo sejam da versão do php que esta disponível. Se por exemplo estiver usando php 8.2 as bibliotecas ficariam por assim:

>> php8.2-nome-da-biblioteca

### Comandos para instalar as extensões no Ubuntu com o PHP 8.5 já instalado
```
sudo apt update
sudo apt install -y \
php8.5-openssl \
php8.5-mbstring \
php8.5-gd \
php8.5-xml \
php8.5-curl \
php8.5-zip
```

### Para ver as extensões/módulos que já estão instaladas rode
```
php -m
```

## Criar certificados para teste através do Ubuntu

### 1. Instalar OpenSSL

#### Verifique se tem OpenSSL
```openssl version```

#### senão tiver instale

```
sudo apt update
sudo apt install openssl
```

### 2. Criar chave privada RSA na pasta certificados
```
cd certificados
openssl genrsa -out private.key 2048
```

#### vai gerar: 
```
private.key
```

### 3. Criar certificado autoassinado com base na chave criada antes
```
openssl req -new -x509 \
-key private.key \
-out certificate.crt \
-days 3650
```

#### Ele perguntará:
> Country Name 
> State
> Locality
> Organization
> Organization Unit Name
> Common Name
> Email

> exemplo de resposta:

> BR
> Minas Gerais
> Montes Claros
> FCO
> FCO
> Marcelo
> marcelo@nossafco.com

### 4. Criar PEM combinado
> O TCPDF gosta disso.

```
cat private.key certificate.crt > certificado.pem
```

### 5. Criar PFX (simula A1)

```
openssl pkcs12 -export \
-out certificado.pfx \
-inkey private.key \
-in certificate.crt
```

> Ele pedirá senha

### Resultado final
```
private.key
certificate.crt
certificado.pem
certificado.pfx
```


# Assinatura PDF

> senha da chave privada  '123456'

## Dependência para PDFs comprimidos

O parser gratuito do FPDI não abre alguns PDFs que usam xref/object streams.
O endpoint tenta normalizar esses documentos automaticamente com `qpdf`, sem
alterar o arquivo enviado. Instale a dependência no servidor Debian/Ubuntu:

## Utilize as dependencias abaixo para trabalhar com png transparente na assiantura

```bash
sudo apt-get update
sudo apt-get install qpdf
```
