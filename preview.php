<?php

if(!isset($_FILES['pdf'])){
    die('Nenhum arquivo enviado');
}

$nome = uniqid().'.pdf';

move_uploaded_file(
    $_FILES['pdf']['tmp_name'],
    'uploads/'.$nome
);

?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<title>Preview PDF</title>

<style>

body{
    margin:0;
    background:#ddd;
    font-family:Arial;
}

.topo{
    position:fixed;
    top:0;
    left:0;
    width:100%;
    background:#fff;
    padding:15px;
    z-index:999;
    border-bottom:1px solid #ccc;
}

#pdf-container{
    margin-top:90px;
}

.page{
    position:relative;
    margin:20px auto;
    width:fit-content;
}

canvas{
    border:1px solid #999;
    display:block;
}

.watermark{
    position:absolute;
    width:120px;
    pointer-events:none;
}

button{
    padding:10px 20px;
    cursor:pointer;
}

</style>
</head>
<body>

<div class="topo">


<form action="processar.php" method="post">

    <input type="hidden" name="arquivo" value="<?= $nome ?>">

    <!-- posição -->

    <input type="hidden" name="x" id="x">
    <input type="hidden" name="y" id="y">

    <!-- página -->

    <input type="hidden" name="pagina" id="pagina">

    <!-- tamanho watermark -->

    <input type="hidden" name="w" id="w">
    <input type="hidden" name="h" id="h">

    <!-- tamanho canvas -->

    <input type="hidden" name="canvas_w" id="canvas_w">
    <input type="hidden" name="canvas_h" id="canvas_h">

    <button type="submit">
        Processar PDF
    </button>

</form>



</div>

<div id="pdf-container"></div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>

<script>

const url = 'uploads/<?= $nome ?>';

const pdfContainer = document.getElementById('pdf-container');

let pdfDoc = null;

const scale = 1.5;

pdfjsLib.getDocument(url).promise.then(async function(pdf){

    pdfDoc = pdf;

    for(let pageNum = 1; pageNum <= pdf.numPages; pageNum++){

        await renderPage(pageNum);

    }

});

async function renderPage(pageNum){

    const page = await pdfDoc.getPage(pageNum);

    const viewport = page.getViewport({scale});

    const pageDiv = document.createElement('div');
    pageDiv.className = 'page';

    const canvas = document.createElement('canvas');

    canvas.width = viewport.width;
    canvas.height = viewport.height;

    pageDiv.appendChild(canvas);

    const watermark = document.createElement('img');

    watermark.src = 'watermark.png';
    watermark.className = 'watermark';
    watermark.style.display = 'none';

    /* tamanho visual da marca */
    watermark.style.width = '120px'; 
    watermark.style.height = 'auto'; 
    pageDiv.appendChild(watermark);
    

    pageDiv.appendChild(watermark);

    pdfContainer.appendChild(pageDiv);

    const ctx = canvas.getContext('2d');

    await page.render({
        canvasContext: ctx,
        viewport: viewport
    }).promise;

    canvas.addEventListener('click', function(e){

        document.querySelectorAll('.watermark')
            .forEach(el => el.style.display = 'none');

        const rect = canvas.getBoundingClientRect();

        const x = e.clientX - rect.left;

        const y = e.clientY - rect.top;

        watermark.style.left = x + 'px';

        watermark.style.top = y + 'px';

        watermark.style.display = 'block';

        /*
            salva posição
        */

        document.getElementById('x').value = x;

        document.getElementById('y').value = y;

        document.getElementById('pagina').value = pageNum;

        /*
            salva tamanho real watermark
        */

        document.getElementById('w').value =
            watermark.offsetWidth;

        document.getElementById('h').value =
            watermark.offsetHeight;

        /*
            salva tamanho canvas
        */

        document.getElementById('canvas_w').value =
            canvas.width;

        document.getElementById('canvas_h').value =
            canvas.height;

    });

}

</script>

</body>
</html>
