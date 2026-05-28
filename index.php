<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<title>Upload PDF</title>

<style>
body{
    font-family:Arial;
    padding:40px;
    background:#f5f5f5;
}

.box{
    background:#fff;
    padding:30px;
    border-radius:10px;
    max-width:500px;
    margin:auto;
}

button{
    padding:10px 20px;
    cursor:pointer;
}
</style>

</head>
<body>

<div class="box">

<h2>Enviar PDF</h2>

<form action="preview.php" method="post" enctype="multipart/form-data">

    <input type="file" name="pdf" accept="application/pdf" required>

    

    <br><br>

    <button type="submit">
        Enviar
    </button>

</form>

</div>

</body>
</html>
