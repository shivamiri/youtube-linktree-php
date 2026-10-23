<?php 
// Assuming config.php is included in index.php before requiring this header
?>
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?= isset($settings['channel_name']) ? htmlspecialchars($settings['channel_name']) : 'SM YouTube Links' ?></title>

    <?php if (defined('GA_MEASUREMENT_ID') && GA_MEASUREMENT_ID !== ''): ?>
    <!-- Google tag (gtag.js) -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=<?= htmlspecialchars(GA_MEASUREMENT_ID) ?>"></script>
    <script>
      window.dataLayer = window.dataLayer || [];
      function gtag(){dataLayer.push(arguments);}
      gtag('js', new Date());

      gtag('config', '<?= htmlspecialchars(GA_MEASUREMENT_ID) ?>');
    </script>
    <?php endif; ?>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        dark: '#0f172a',
                        darker: '#020617',
                        card: '#1e293b',
                        primary: '#3b82f6'
                    }
                }
            }
        }
    </script>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        /* Globally Disable Text Selection */
        body {
            -webkit-touch-callout: none;
            -webkit-user-select: none;
            -khtml-user-select: none;
            -moz-user-select: none;
            -ms-user-select: none;
            user-select: none;
            overscroll-behavior-y: none; /* Prevent pull-to-refresh */
        }
        
        /* Hide scrollbar for a cleaner mobile app look */
        ::-webkit-scrollbar {
            width: 0px;
            background: transparent;
        }
        
        /* Smooth Hover Animation for Video Cards */
        .hover-card {
            transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
        }
        .hover-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 20px -5px rgba(0, 0, 0, 0.4);
            border-color: rgba(59, 130, 246, 0.5); /* primary border glow */
        }
    </style>
</head>
<body class="bg-darker text-gray-200 min-h-screen flex flex-col items-center p-4 sm:p-6 lg:p-8 font-sans antialiased">
    
    <div class="w-full max-w-2xl flex-grow flex flex-col mt-4">
