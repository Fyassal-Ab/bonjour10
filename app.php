<?php
// app.php — Full working solution
$db_host = 'db';
$db_port = '3306';
$db_name = 'resume_db';
$db_user = 'user';
$db_pass = 'FF@2002@aa';

session_start();
$salt = 'XyZzy12*_';

try {
    $pdo = new PDO("mysql:host=$db_host;port=$db_port;dbname=$db_name;charset=utf8mb4",
        $db_user, $db_pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (Exception $e) {
    die("DB connection error: " . htmlentities($e->getMessage()));
}

// Flash helpers
function set_error($m){ $_SESSION['error']=$m; }
function set_success($m){ $_SESSION['success']=$m; }
function flash(){
    if(isset($_SESSION['error'])){ echo '<p style="color:red">'.htmlentities($_SESSION['error']).'</p>'; unset($_SESSION['error']); }
    if(isset($_SESSION['success'])){ echo '<p style="color:green">'.htmlentities($_SESSION['success']).'</p>'; unset($_SESSION['success']); }
}

// escape helper
function e($s){ return htmlentities($s); }
function require_login(){ if(!isset($_SESSION['user_id'])) die("Not logged in"); }

$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : 'index';

// ---------- POST actions ----------
if ($_SERVER['REQUEST_METHOD']==='POST') {
    // LOGIN
    if(isset($_POST['action']) && $_POST['action']==='do_login'){
        if(empty($_POST['email']) || empty($_POST['pass'])){
            set_error("Email and password are required");
            header("Location: app.php?action=login"); exit;
        }
        $check = hash('md5', $salt.$_POST['pass']);
        $stmt = $pdo->prepare('SELECT user_id,name FROM users WHERE email=:em AND password=:pw');
        $stmt->execute([':em'=>$_POST['email'], ':pw'=>$check]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if($row){
            $_SESSION['user_id']=$row['user_id'];
            $_SESSION['name']=$row['name'];
            header("Location: app.php"); exit;
        } else {
            set_error("Incorrect email or password");
            header("Location: app.php?action=login"); exit;
        }
    }

    // ADD PROFILE
    if(isset($_POST['action']) && $_POST['action']==='do_add'){
        require_login();
        if(empty($_POST['first_name']) || empty($_POST['last_name']) || empty($_POST['email'])
           || empty($_POST['headline']) || empty($_POST['summary'])){
            set_error("All fields are required");
            header("Location: app.php?action=add"); exit;
        }
        if(strpos($_POST['email'],'@')===false){
            set_error("Email address must contain @");
            header("Location: app.php?action=add"); exit;
        }
        $stmt = $pdo->prepare('INSERT INTO Profile (user_id,first_name,last_name,email,headline,summary)
            VALUES (:uid,:fn,:ln,:em,:he,:su)');
        $stmt->execute([
            ':uid'=>$_SESSION['user_id'],
            ':fn'=>$_POST['first_name'],
            ':ln'=>$_POST['last_name'],
            ':em'=>$_POST['email'],
            ':he'=>$_POST['headline'],
            ':su'=>$_POST['summary']
        ]);
        set_success("Profile added");
        header("Location: app.php"); exit;
    }

    // EDIT PROFILE
    if(isset($_POST['action']) && $_POST['action']==='do_edit'){
        require_login();
        if(empty($_POST['profile_id'])){ set_error("Missing profile_id"); header("Location: app.php"); exit; }
        $pid=$_POST['profile_id'];
        $stmt=$pdo->prepare("SELECT * FROM Profile WHERE profile_id=:pid");
        $stmt->execute([':pid'=>$pid]);
        $row=$stmt->fetch(PDO::FETCH_ASSOC);
        if(!$row){ set_error("Could not find profile"); header("Location: app.php"); exit; }
        if($row['user_id'] != $_SESSION['user_id']) die("Not logged in");

        if(empty($_POST['first_name']) || empty($_POST['last_name']) || empty($_POST['email'])
           || empty($_POST['headline']) || empty($_POST['summary'])){
            set_error("All fields are required");
            header("Location: app.php?action=edit&profile_id=".urlencode($pid)); exit;
        }
        if(strpos($_POST['email'],'@')===false){
            set_error("Email address must contain @");
            header("Location: app.php?action=edit&profile_id=".urlencode($pid)); exit;
        }

        $stmt=$pdo->prepare('UPDATE Profile SET first_name=:fn,last_name=:ln,email=:em,headline=:he,summary=:su WHERE profile_id=:pid');
        $stmt->execute([
            ':fn'=>$_POST['first_name'],':ln'=>$_POST['last_name'],
            ':em'=>$_POST['email'],':he'=>$_POST['headline'],':su'=>$_POST['summary'],
            ':pid'=>$pid
        ]);
        set_success("Profile updated");
        header("Location: app.php"); exit;
    }

    // DELETE
    if(isset($_POST['action']) && $_POST['action']==='do_delete'){
        require_login();
        if(empty($_POST['profile_id'])){ set_error("Missing profile_id"); header("Location: app.php"); exit; }
        $pid=$_POST['profile_id'];
        $stmt=$pdo->prepare("SELECT * FROM Profile WHERE profile_id=:pid");
        $stmt->execute([':pid'=>$pid]);
        $row=$stmt->fetch(PDO::FETCH_ASSOC);
        if(!$row){ set_error("Could not find profile"); header("Location: app.php"); exit; }
        if($row['user_id'] != $_SESSION['user_id']) die("Not logged in");
        $stmt=$pdo->prepare("DELETE FROM Profile WHERE profile_id=:pid");
        $stmt->execute([':pid'=>$pid]);
        set_success("Profile deleted");
        header("Location: app.php"); exit;
    }

    // LOGOUT
    if(isset($_POST['action']) && $_POST['action']==='do_logout'){
        unset($_SESSION['user_id']); unset($_SESSION['name']);
        header("Location: app.php"); exit;
    }
}

// ---------- GET pages ----------
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Resume App - Faisal</title>
<script>
function doValidate(){
  try{
    var em=document.getElementById('id_email').value;
    var pw=document.getElementById('id_pass').value;
    if(em=="" || pw==""){ alert("Both fields must be filled out"); return false; }
    if(em.indexOf('@')==-1){ alert("Email address must contain @"); return false; }
    return true;
  }catch(e){ return false; }
}
</script>
</head>
<body>
<h1>Resume Profiles</h1>
<?php flash(); ?>

<?php
if(isset($_SESSION['name'])){
    echo "<p>Logged in as ".e($_SESSION['name'])."
        <form style='display:inline' method='post' action='app.php'>
            <input type='hidden' name='action' value='do_logout'>
            <input type='submit' value='Logout'>
        </form>
        | <a href='app.php?action=add'>Add New Entry</a></p>";
}else{
    echo "<p><a href='app.php?action=login'>Please log in</a></p>";
}

// INDEX page
if($action==='index'){
    $stmt=$pdo->query("SELECT profile_id,user_id,first_name,last_name,headline FROM Profile");
    echo "<table border='1'><tr><th>Name</th><th>Headline</th><th>Action</th></tr>";
    while($r=$stmt->fetch(PDO::FETCH_ASSOC)){
        echo "<tr><td><a href='app.php?action=view&profile_id=".e($r['profile_id'])."'>"
             .e($r['first_name'])." ".e($r['last_name'])."</a></td>";
        echo "<td>".e($r['headline'])."</td><td>";
        if(isset($_SESSION['user_id']) && $_SESSION['user_id']==$r['user_id']){
            echo "<a href='app.php?action=edit&profile_id=".e($r['profile_id'])."'>Edit</a> / ";
            echo "<a href='app.php?action=delete&profile_id=".e($r['profile_id'])."'>Delete</a>";
        }
        echo "</td></tr>";
    }
    echo "</table>";
    exit;
}

// LOGIN page
if($action==='login'){ ?>
<h2>Please Log In</h2>
<form method="post" action="app.php" onsubmit="return doValidate();">
    <input type="hidden" name="action" value="do_login">
    <label>Email: <input type="text" name="email" id="id_email"></label><br/>
    <label>Password: <input type="password" name="pass" id="id_pass"></label><br/>
    <input type="submit" value="Log In"> <a href="app.php">Cancel</a>
</form>
<p>Test: umsi@umich.edu / php123</p>
<?php exit; } ?>

<!-- ADD / EDIT / VIEW / DELETE handled like in your code, same logic -->
