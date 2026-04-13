<?php
// hash_generator.php
$hash = "";
$plain = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $plain = $_POST["password"] ?? "";
    if (!empty($plain)) {
        $hash = password_hash($plain, PASSWORD_DEFAULT);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Password Hash Generator</title>

  <!-- Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Merriweather:wght@700;900&display=swap" rel="stylesheet">

  <style>
    body{
      margin:0;
      min-height:100vh;
      display:flex;
      justify-content:center;
      align-items:center;
      background:#f5f7fb;
      font-family:'Inter', sans-serif;
      padding:20px;
    }

    .card{
      width:100%;
      max-width:520px;
      background:#fff;
      border-radius:16px;
      box-shadow:0 12px 30px rgba(0,0,0,0.12);
      padding:28px;
    }

    h1{
      font-family:'Merriweather', serif;
      font-size:26px;
      margin:0 0 10px 0;
      color:#162447;
    }

    p{
      margin:0 0 18px 0;
      color:#444;
      font-size:14px;
      line-height:1.5;
    }

    label{
      font-weight:600;
      font-size:14px;
      color:#162447;
      display:block;
      margin-bottom:8px;
    }

    input{
      width:90%;
      padding:12px 14px;
      border:1px solid #d9dce3;
      border-radius:10px;
      outline:none;
      font-size:15px;
    }

    input:focus{
      border-color:#1f4068;
      box-shadow:0 0 0 3px rgba(31,64,104,0.15);
    }

    button{
      margin-top:14px;
      width:100%;
      padding:12px;
      border:none;
      border-radius:10px;
      cursor:pointer;
      background:linear-gradient(135deg,#1f4068,#162447);
      color:#fff;
      font-size:15px;
      font-weight:600;
      transition:0.3s;
    }

    button:hover{
      transform:translateY(-2px);
      box-shadow:0 10px 20px rgba(0,0,0,0.2);
    }

    .output{
      margin-top:18px;
    }

    textarea{
      width:100%;
      min-height:130px;
      padding:12px 14px;
      border:1px solid #d9dce3;
      border-radius:10px;
      font-size:13px;
      resize:none;
      background:#f9fafc;
    }

    .copy-btn{
      margin-top:10px;
      background:#ff4d4f;
    }

    .copy-btn:hover{
      background:#e03b3b;
    }

    .note{
      margin-top:14px;
      font-size:12px;
      color:#666;
    }
  </style>
</head>
<body>

  <div class="card">
    <h1>Password Hash Generator</h1>
    <p>Enter a password below. It will generate a secure hashed password that you can insert into your <b>admins</b> table manually.</p>

    <form method="POST">
      <label for="password">Plain Password</label>
      <input type="text" id="password" name="password" placeholder="Type password here..." value="<?php echo htmlspecialchars($plain); ?>" required>
      <button type="submit">Generate Hash</button>
    </form>

    <?php if(!empty($hash)): ?>
      <div class="output">
        <label>Generated Hash</label>
        <textarea id="hashOutput" readonly><?php echo htmlspecialchars($hash); ?></textarea>
        <button class="copy-btn" onclick="copyHash()">Copy Hash</button>
        <div class="note">✅ Copy this hash and paste it into your database.</div>
      </div>
    <?php endif; ?>
  </div>

<script>
function copyHash(){
  const textarea = document.getElementById("hashOutput");
  textarea.select();
  textarea.setSelectionRange(0, 999999);
  navigator.clipboard.writeText(textarea.value);
  alert("Hash copied!");
}
</script>

</body>
</html>
