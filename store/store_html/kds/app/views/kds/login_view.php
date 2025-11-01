<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TopTea KDS - Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="css/kds_login.css?v=<?php echo time(); ?>">
</head>
<body>
    <div class="login-container">
        <div class="login-box">
            <h2 class="text-center mb-1">TOPTEA</h2>
            <h5 class="text-center text-white-50 mb-4" data-i18n-key="title_sub">制茶助手</h5>
            
            <?php if (isset($_GET['error'])): ?>
                <div class="alert alert-danger" role="alert" data-i18n-key="error_invalid_credentials">
                    无效的用户名、密码或门店码。
                </div>
            <?php endif; ?>

            <form action="api/kds_login_handler.php" method="POST">
                <div class="form-floating mb-3">
                    <input type="text" class="form-control" id="store_code" name="store_code" placeholder="门店码" required>
                    <label for="store_code" data-i18n-key="label_store_code">门店码</label>
                </div>
                <div class="form-floating mb-3">
                    <input type="text" class="form-control" id="username" name="username" placeholder="用户名" required>
                    <label for="username" data-i18n-key="label_username">用户名</label>
                </div>
                <div class="form-floating mb-4">
                    <input type="password" class="form-control" id="password" name="password" placeholder="密码" required>
                    <label for="password" data-i18n-key="label_password">密码</label>
                </div>
                <div class="d-grid">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="bi bi-box-arrow-in-right me-2"></i> <span data-i18n-key="btn_login">登 录</span>
                    </button>
                </div>
            </form>

            <div class="lang-switch-footer mt-4 text-center">
                <span class="lang-flag" data-lang="zh-CN"><svg class="flag" viewBox="0 0 30 20"><rect fill="#DE2910" height="20" width="30"></rect><text fill="#FFDE00" font-size="8.5" x="6" y="8">★</text><text fill="#FFDE00" font-size="3.8" x="12.5" y="4.5">★</text><text fill="#FFDE00" font-size="3.8" x="14.5" y="8">★</text><text fill="#FFDE00" font-size="3.8" x="12.5" y="11.5">★</text><text fill="#FFDE00" font-size="3.8" x="9.8" y="9.5">★</text></svg></span>
                <span class="lang-flag" data-lang="es-ES"><svg class="flag" viewBox="0 0 30 20"><rect fill="#AA151B" height="20" width="30"></rect><rect y="5" width="30" height="10" fill="#F1BF00"></rect></svg></span>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="js/kds_login.js?v=<?php echo time(); ?>"></script>
</body>
</html>