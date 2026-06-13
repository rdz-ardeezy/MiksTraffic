<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> - MikroTik Traffic Monitoring</title>
    
    <!-- Google Fonts: Inter & Outfit -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Outfit:wght@400;600;700&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Custom CSS -->
    <?php 
    $base = (strpos($_SERVER['PHP_SELF'], '/admin/') !== false) ? '../' : '';
    ?>
    <link rel="stylesheet" href="<?php echo $base; ?>assets/style.css">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <nav class="glass-nav">
        <div class="container">
            <div class="nav-content">
                <div class="brand">
                    <i class="fas fa-chart-line"></i>
                    <span><?php echo APP_NAME; ?></span>
                </div>
                <div class="nav-status">
                    <span class="status-dot"></span>
                    <span id="system-status">System Live</span>
                </div>
            </div>
        </div>
    </nav>
    <main class="container">
