<?php
// app.php — نسخة مُجمعة لجميع الشاشات
// ---- إعدادات قاعدة البيانات: عدّلها حسب بيئتك ----
$db_host = 'db';
$db_port = '3306';
$db_name = 'resume_db';
$db_user = 'user';
$db_pass = 'FF@2002@aa';

// ---- إعداد عام ----
session_start();
$salt = 'XyZzy12*_';

// PDO
try {
    $pdo = new PDO("mysql:host=$db_host;port=$db_port;dbname=$db_name;charset=utf8mb4",
        $db_user, $db_pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (Exception $e) {
    die("DB connection error: " . htmlentities($e->getMessage()));
}

// Flash helpers
function set_error($m){ $_SESSION['error'] = $m; }
function set_success($m){ $_SESSION['success'] = $m; }
function flash(){
    if(isset($_SESSION['error'])){ echo '<p style="color:red">'.htmlentities($_SESSION['error']).'</p>'; unset($_SESSION['error']); }
    if(isset($_SESSION['success'])){ echo '<p style="color:green">'.htmlentities($_SESSION['success']).'</p>'; unset($_SESSION['success']); }
}

// Route (action)
$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : 'index';

// Helper: require login
function require_login(){
    if(!isset($_SESSION['user_id'])) die("Not logged in");
}

// Helper: escape
function e($s){ return htmlentities($s); }

// ------------------ Handle POST actions first (follow POST-Redirect-GET) ------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // LOGIN
    if (isset($_POST['action']) && $_POST['action'] === 'do_login') {
        if ( !isset($_POST['email']) || !isset($_POST['pass']) || strlen($_POST['email'])<1 || strlen($_POST['pass'])<1 ) {
            set_error("Email and password are required");
            header("Location: app.php?action=login"); exit;
        }
        $check = hash('md5', $salt . $_POST['pass']);
        $stmt = $pdo->prepare('SELECT user_id, name FROM users WHERE email = :em AND password = :pw');
        $stmt->execute(array(':em'=>$_POST['email'], ':pw'=>$check));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row !== false) {
            $_SESSION['name'] = $row['name'];
            $_SESSION['user_id'] = $row['user_id'];
            header("Location: app.php"); exit;
        } else {
            set_error("Incorrect email or password");
            header("Location: app.php?action=login"); exit;
        }
    }

    // ADD PROFILE
    if (isset($_POST['action']) && $_POST['action'] === 'do_add') {
        require_login();
        // server-side validation
        if (empty($_POST['first_name']) || empty($_POST['last_name']) || empty($_POST['email'])
            || empty($_POST['headline']) || empty($_POST['summary'])) {
            set_error("All fields are required");
            header("Location: app.php?action=add"); exit;
        }
        if (strpos($_POST['email'], '@') === false) {
            set_error("Email address must contain @");
            header("Location: app.php?action=add"); exit;
        }
        $stmt = $pdo->prepare('INSERT INTO Profile (user_id, first_name, last_name, email, headline, summary)
            VALUES (:uid, :fn, :ln, :em, :he, :su)');
        $stmt->execute(array(
            ':uid' => $_SESSION['user_id'],
            ':fn' => $_POST['first_name'],
            ':ln' => $_POST['last_name'],
            ':em' => $_POST['email'],
            ':he' => $_POST['headline'],
            ':su' => $_POST['summary']
        ));
        set_success("Profile added");
        header("Location: app.php"); exit;
    }

    // EDIT PROFILE
    if (isset($_POST['action']) && $_POST['action'] === 'do_edit') {
        require_login();
        if (empty($_POST['profile_id'])) { set_error("Missing profile_id"); header("Location: app.php"); exit; }
        $pid = $_POST['profile_id'];
        // fetch and check ownership
        $stmt = $pdo->prepare("SELECT * FROM Profile WHERE profile_id = :pid");
        $stmt->execute(array(':pid'=>$pid));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) { set_error("Could not find profile"); header("Location: app.php"); exit; }
        if ($row['user_id'] != $_SESSION['user_id']) die("Not logged in");

        // validation
        if (empty($_POST['first_name']) || empty($_POST['last_name']) || empty($_POST['email'])
            || empty($_POST['headline']) || empty($_POST['summary'])) {
            set_error("All fields are required");
            header("Location: app.php?action=edit&profile_id=".urlencode($pid)); exit;
        }
        if (strpos($_POST['email'], '@') === false) {
            set_error("Email address must contain @");
            header("Location: app.php?action=edit&profile_id=".urlencode($pid)); exit;
        }

        $stmt = $pdo->prepare('UPDATE Profile SET first_name = :fn, last_name = :ln,
            email = :em, headline = :he, summary = :su WHERE profile_id = :pid');
        $stmt->execute(array(
            ':fn'=>$_POST['first_name'], ':ln'=>$_POST['last_name'],
            ':em'=>$_POST['email'], ':he'=>$_POST['headline'], ':su'=>$_POST['summary'],
            ':pid'=>$pid
        ));
        set_success("Profile updated");
        header("Location: app.php"); exit;
    }

    // DELETE PROFILE (POST)
    if (isset($_POST['action']) && $_POST['action'] === 'do_delete') {
        require_login();
        if (empty($_POST['profile_id'])) { set_error("Missing profile_id"); header("Location: app.php"); exit; }
        $pid = $_POST['profile_id'];
        $stmt = $pdo->prepare("SELECT * FROM Profile WHERE profile_id = :pid");
        $stmt->execute(array(':pid'=>$pid));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) { set_error("Could not find profile"); header("Location: app.php"); exit; }
        if ($row['user_id'] != $_SESSION['user_id']) die("Not logged in");

        $stmt = $pdo->prepare("DELETE FROM Profile WHERE profile_id = :pid");
        $stmt->execute(array(':pid'=>$pid));
        set_success("Profile deleted");
        header("Location: app.php"); exit;
    }

    // LOGOUT
    if (isset($_POST['action']) && $_POST['action'] === 'do_logout') {
        unset($_SESSION['name']); unset($_SESSION['user_id']);
        header("Location: app.php"); exit;
    }
}

// ------------------ Render pages (GET) ------------------
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Resume App - Faisal</title>
  <script>
  // JS validation for login (used in login form)
  function doValidate(){
      try {
          var em = document.getElementById('id_email').value;
          var pw = document.getElementById('id_pass').value;
          if (em == null || em == "" || pw == null || pw == "") {
              alert("Both fields must be filled out");
              return false;
          }
          if (em.indexOf('@') == -1) {
              alert("Email address must contain @");
              return false;
          }
          return true;
      } catch(e) { return false; }
  }
  </script>
</head>
<body>
<?php
// simple navigation
echo "<h1>Resume Profiles</h1>";
flash();

// show login/logout/add links
if (isset($_SESSION['name'])) {
    echo "<p>Logged in as ".e($_SESSION['name'])." 
        <form style='display:inline' method='post' action='app.php'>
          <input type='hidden' name='action' value='do_logout'>
          <input type='submit' value='Logout'>
        </form>
        | <a href='app.php?action=add'>Add New</a></p>";
} else {
    echo "<p><a href='app.php?action=login'>Please log in</a></p>";
}

// Router views:
if ($action === 'index') {
    // list all profiles
    $stmt = $pdo->query("SELECT profile_id, user_id, first_name, last_name, headline FROM Profile");
    echo "<table border='1'><tr><th>Name</th><th>Headline</th><th>Action</th></tr>";
    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "<tr><td><a href='app.php?action=view&profile_id=".e($r['profile_id'])."'>"
            .e($r['first_name'])." ".e($r['last_name'])."</a></td>";
        echo "<td>".e($r['headline'])."</td><td>";
        if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $r['user_id']) {
            echo "<a href='app.php?action=edit&profile_id=".e($r['profile_id'])."'>Edit</a> / ";
            echo "<a href='app.php?action=delete&profile_id=".e($r['profile_id'])."'>Delete</a>";
        }
        echo "</td></tr>";
    }
    echo "</table>";
    exit;
}

// LOGIN page
if ($action === 'login') {
    ?>
    <h2>Please Log In</h2>
    <form method="post" action="app.php" onsubmit="return doValidate();">
      <input type="hidden" name="action" value="do_login">
      <label>Email: <input type="text" name="email" id="id_email"></label><br/>
      <label>Password: <input type="password" name="pass" id="id_pass"></label><br/>
      <input type="submit" value="Log In">
      <a href="app.php">Cancel</a>
    </form>
    <p>Test: umsi@umich.edu / php123</p>
    <?php
    exit;
}

// ADD page
if ($action === 'add') {
    require_login();
    ?>
    <h2>Add Profile</h2>
    <?php flash(); ?>
    <form method="post" action="app.php">
      <input type="hidden" name="action" value="do_add">
      <p>First name:<br/><input type="text" name="first_name" size="60"></p>
      <p>Last name:<br/><input type="text" name="last_name" size="60"></p>
      <p>Email:<br/><input type="text" name="email" size="60"></p>
      <p>Headline:<br/><input type="text" name="headline" size="80"></p>
      <p>Summary:<br/><textarea name="summary" rows="8" cols="80"></textarea></p>
      <p><input type="submit" value="Add"/> <a href="app.php">Cancel</a></p>
    </form>
    <?php exit;
}

// VIEW page
if ($action === 'view') {
    if (!isset($_GET['profile_id'])) { set_error("Missing profile_id"); header("Location: app.php"); exit; }
    $stmt = $pdo->prepare("SELECT * FROM Profile WHERE profile_id = :pid");
    $stmt->execute(array(':pid'=>$_GET['profile_id']));
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row === false) { set_error("Could not find profile"); header("Location: app.php"); exit; }
    echo "<h2>Profile Information</h2>";
    echo "<p>First Name: ".e($row['first_name'])."</p>";
    echo "<p>Last Name: ".e($row['last_name'])."</p>";
    echo "<p>Email: ".e($row['email'])."</p>";
    echo "<p>Headline:<br/>".e($row['headline'])."</p>";
    echo "<p>Summary:<br/>".nl2br(e($row['summary']))."</p>";
    echo "<p><a href='app.php'>Done</a></p>";
    exit;
}

// EDIT page
if ($action === 'edit') {
    require_login();
    if (!isset($_GET['profile_id'])) { set_error("Missing profile_id"); header("Location: app.php"); exit; }
    $stmt = $pdo->prepare("SELECT * FROM Profile WHERE profile_id = :pid");
    $stmt->execute(array(':pid'=>$_GET['profile_id']));
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row === false) { set_error("Could not find profile"); header("Location: app.php"); exit; }
    if ($row['user_id'] != $_SESSION['user_id']) die("Not logged in");
    ?>
    <h2>Edit Profile</h2>
    <?php flash(); ?>
    <form method="post" action="app.php">
      <input type="hidden" name="action" value="do_edit">
      <input type="hidden" name="profile_id" value="<?= e($row['profile_id']) ?>">
      <p>First name:<br/><input type="text" name="first_name" size="60" value="<?= e($row['first_name']) ?>"></p>
      <p>Last name:<br/><input type="text" name="last_name" size="60" value="<?= e($row['last_name']) ?>"></p>
      <p>Email:<br/><input type="text" name="email" size="60" value="<?= e($row['email']) ?>"></p>
      <p>Headline:<br/><input type="text" name="headline" size="80" value="<?= e($row['headline']) ?>"></p>
      <p>Summary:<br/><textarea name="summary" rows="8" cols="80"><?= e($row['summary']) ?></textarea></p>
      <p><input type="submit" value="Save"/> <a href="app.php">Cancel</a></p>
    </form>
    <?php exit;
}

// DELETE confirmation page (GET)
if ($action === 'delete') {
    require_login();
    if (!isset($_GET['profile_id'])) { set_error("Missing profile_id"); header("Location: app.php"); exit; }
    $stmt = $pdo->prepare("SELECT * FROM Profile WHERE profile_id = :pid");
    $stmt->execute(array(':pid'=>$_GET['profile_id']));
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row === false) { set_error("Could not find profile"); header("Location: app.php"); exit; }
    if ($row['user_id'] != $_SESSION['user_id']) die("Not logged in");
    ?>
    <h2>Delete Profile</h2>
    <p>First Name: <?= e($row['first_name']) ?></p>
    <p>Last Name: <?= e($row['last_name']) ?></p>
    <form method="post" action="app.php">
      <input type="hidden" name="action" value="do_delete">
      <input type="hidden" name="profile_id" value="<?= e($row['profile_id']) ?>">
      <input type="submit" value="Delete"> <a href="app.php">Cancel</a>
    </form>
    <?php exit;
}

// Fallback
set_error("Unknown action");
header("Location: app.php");
exit;
?>
</body>
</html>
