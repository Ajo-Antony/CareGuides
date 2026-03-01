<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kids Games Dashboard - CareGuides</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #4a6fa5;
            --secondary-color: #166088;
            --accent-color: #17a2b8;
            --light-color: #f8f9fa;
            --dark-color: #343a40;
            --success-color: #28a745;
            --warning-color: #ffc107;
            --danger-color: #dc3545;
        }
        
        body {
            background-color: #f5f7fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .dashboard-header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 40px 0;
            margin-bottom: 30px;
        }
        
        .welcome-card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.08);
            transition: transform 0.3s;
            height: 100%;
            border-left: 4px solid var(--primary-color);
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        
        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            margin-bottom: 15px;
        }
        
        .stat-number {
            font-size: 1.8rem;
            font-weight: bold;
            color: var(--primary-color);
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #666;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 500;
        }
        
        .section-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.08);
            height: 100%;
        }
        
        .section-title {
            color: var(--primary-color);
            font-weight: 600;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .game-card {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 3px 15px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            height: 100%;
            border: 1px solid #e9ecef;
        }
        
        .game-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 10px 30px rgba(74, 111, 165, 0.15);
            border-color: var(--primary-color);
        }
        
        .game-header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 20px;
            text-align: center;
            min-height: 180px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }
        
        .game-icon {
            width: 70px;
            height: 70px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 30px;
            margin-bottom: 15px;
            border: 3px solid rgba(255, 255, 255, 0.3);
        }
        
        .game-content {
            padding: 20px;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
        }
        
        .game-title {
            font-weight: 600;
            color: var(--dark-color);
            margin-bottom: 10px;
            font-size: 1.2rem;
        }
        
        .game-description {
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 15px;
            flex-grow: 1;
        }
        
        .game-stats {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
            flex-wrap: wrap;
        }
        
        .game-stat {
            background: #f8f9fa;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            color: var(--primary-color);
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .play-btn {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 8px;
            font-weight: 600;
            width: 100%;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            text-decoration: none;
        }
        
        .play-btn:hover {
            background: linear-gradient(135deg, var(--secondary-color), var(--primary-color));
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(74, 111, 165, 0.3);
            text-decoration: none;
        }
        
        .quick-action {
            display: flex;
            align-items: center;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 10px;
            margin-bottom: 10px;
            text-decoration: none;
            color: #333;
            transition: all 0.3s;
        }
        
        .quick-action:hover {
            background: var(--primary-color);
            color: white;
            transform: translateX(5px);
            text-decoration: none;
        }
        
        .quick-action-icon {
            width: 50px;
            height: 50px;
            background: rgba(74, 111, 165, 0.1);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            font-size: 20px;
        }
        
        .activity-item {
            padding: 12px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .activity-item:last-child {
            border-bottom: none;
        }
        
        .activity-time {
            font-size: 0.8rem;
            color: #999;
        }
        
        .feature-badge {
            padding: 5px 12px;
            border-radius: 15px;
            font-size: 0.8rem;
            margin-right: 5px;
            margin-bottom: 5px;
            background: #e0f2fe;
            color: #0369a1;
            border: 1px solid #bae6fd;
        }
        
        .empty-state {
            text-align: center;
            padding: 30px;
            color: #6c757d;
        }
        
        .empty-state-icon {
            font-size: 3rem;
            margin-bottom: 15px;
            opacity: 0.5;
        }
        
        .modal-content {
            border-radius: 15px;
            border: none;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        
        .modal-header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            border-radius: 15px 15px 0 0;
            border: none;
            padding: 20px 30px;
        }
        
        .modal-title {
            font-weight: 600;
            font-size: 1.3rem;
        }
        
        .modal-close {
            background: rgba(255,255,255,0.2);
            border: none;
            color: white;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .modal-close:hover {
            background: rgba(255,255,255,0.3);
        }
        
        .canvas-container {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin: 20px 0;
            border: 1px solid #e9ecef;
        }
        
        #drawingCanvas {
            border: 2px solid #e9ecef;
            border-radius: 8px;
            background: white;
            cursor: crosshair;
            width: 100%;
            max-width: 800px;
            display: block;
            margin: 0 auto;
        }
        
        .drawing-tools {
            display: flex;
            gap: 10px;
            margin-top: 15px;
            flex-wrap: wrap;
            justify-content: center;
        }
        
        .tool-btn {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            border: 2px solid #e9ecef;
            background: white;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .tool-btn:hover, .tool-btn.active {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }
        
        .color-preview {
            width: 30px;
            height: 30px;
            border-radius: 6px;
            border: 2px solid #e9ecef;
            cursor: pointer;
        }
        
        .color-palette {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            justify-content: center;
        }
        
        .color-swatch {
            width: 25px;
            height: 25px;
            border-radius: 6px;
            cursor: pointer;
            border: 2px solid #e9ecef;
        }
        
        .color-swatch:hover {
            transform: scale(1.1);
        }
        
        .memory-card {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            cursor: pointer;
            user-select: none;
            color: white;
            transition: all 0.3s ease;
        }
        
        .memory-card.flipped {
            background: white;
            color: var(--primary-color);
            border: 2px solid var(--primary-color);
        }
        
        .memory-card.matched {
            background: linear-gradient(135deg, var(--success-color), #20c997);
            color: white;
            cursor: default;
        }
        
        .tictactoe-cell {
            width: 80px;
            height: 80px;
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            font-size: 2.5rem;
            font-weight: bold;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .tictactoe-cell:hover {
            background: #e9ecef;
        }
        
        .math-problem {
            font-size: 2.5rem;
            font-weight: bold;
            color: var(--primary-color);
            margin: 20px 0;
            text-align: center;
        }
        
        .math-option {
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 15px;
            font-size: 1.5rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            text-align: center;
        }
        
        .math-option:hover {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }
        
        .math-option.correct {
            background: linear-gradient(135deg, var(--success-color), #20c997);
            color: white;
            border-color: var(--success-color);
        }
        
        .math-option.incorrect {
            background: linear-gradient(135deg, var(--danger-color), #dc3545);
            color: white;
            border-color: var(--danger-color);
        }
        
        .alphabet-canvas {
            position: relative;
            width: 300px;
            height: 200px;
            margin: 0 auto;
            background: linear-gradient(135deg, #f0f9ff, #e0f2fe);
            border-radius: 10px;
            overflow: hidden;
        }
        
        .alphabet-letter {
            position: absolute;
            width: 30px;
            height: 30px;
            background: white;
            border: 2px solid var(--primary-color);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 1.2rem;
            color: var(--primary-color);
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .alphabet-letter:hover {
            transform: scale(1.2);
            background: var(--primary-color);
            color: white;
        }
        
        .alphabet-letter.found {
            background: var(--success-color);
            color: white;
            border-color: var(--success-color);
        }
        
        .animal-sound-btn {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 10px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin: 20px auto;
        }
        
        .animal-sound-btn:hover {
            transform: scale(1.05);
            box-shadow: 0 5px 15px rgba(74, 111, 165, 0.3);
        }
        
        .animal-option {
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 15px;
            cursor: pointer;
            transition: all 0.2s;
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .animal-option:hover {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }
        
        .animal-option.correct {
            background: linear-gradient(135deg, var(--success-color), #20c997);
            color: white;
            border-color: var(--success-color);
        }
        
        .animal-option.incorrect {
            background: linear-gradient(135deg, var(--danger-color), #dc3545);
            color: white;
            border-color: var(--danger-color);
        }
        
        .drawing-challenge {
            background: linear-gradient(135deg, #f0f9ff, #e0f2fe);
            border-radius: 10px;
            padding: 15px;
            margin: 20px 0;
            text-align: center;
            border: 2px dashed var(--primary-color);
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
        
        .pulse-animation {
            animation: pulse 2s infinite;
        }
        
        @media (max-width: 768px) {
            .dashboard-header {
                padding: 30px 0;
            }
            
            .welcome-card {
                padding: 20px;
            }
            
            .game-header {
                min-height: 150px;
            }
            
            .game-icon {
                width: 60px;
                height: 60px;
                font-size: 24px;
            }
            
            .memory-card {
                width: 60px;
                height: 60px;
                font-size: 1.5rem;
            }
            
            .tictactoe-cell {
                width: 60px;
                height: 60px;
                font-size: 2rem;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark" style="background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center" href="#">
                <i class="bi bi-controller me-2"></i>
                <span class="fw-bold">CareGuides Games</span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="dashboard.php"><i class="bi bi-house-door me-1"></i> Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="story.html"><i class="bi bi-people me-1"></i> Story Board</a>
                    </li>
                  
                </ul>
            </div>
        </div>
    </nav>
    
    <!-- Dashboard Header -->
    <div class="dashboard-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="display-5 fw-bold">Fun Learning Games for Kids</h1>
                    <p class="lead mb-0">
                        <?php echo date('l, F j, Y'); ?> • 
                        <span class="badge bg-light text-primary">Educational Games</span>
                    </p>
                </div>
                <div class="col-md-4 text-md-end">
                    <button class="btn btn-light btn-lg" onclick="showParentInfo()">
                        <i class="bi bi-info-circle me-2"></i> Parent Info
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="container pb-5">
        <!-- Welcome Card -->
        <div class="welcome-card">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <h3 class="mb-3">Welcome to Educational Games!</h3>
                    <p class="text-muted mb-4">
                        Our games are specially designed to help children develop important skills while having fun. 
                        Each game focuses on different areas of development including memory, problem-solving, 
                        and creative expression.
                    </p>
                    <div class="d-flex flex-wrap gap-2">
                        <button class="btn btn-primary px-4" onclick="startDrawingGame()">
                            <i class="fas fa-paint-brush me-2"></i> Start Drawing
                        </button>
                        <button class="btn btn-outline-primary px-4" onclick="showAllGames()">
                            <i class="bi bi-grid me-2"></i> View All Games
                        </button>
                        <button class="btn btn-outline-secondary px-4" onclick="showParentInfo()">
                            <i class="bi bi-people me-2"></i> For Parents
                        </button>
                    </div>
                </div>
                <div class="col-lg-4 text-center mt-4 mt-lg-0">
                    <div class="display-1 text-primary">
                        <i class="bi bi-joystick"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Statistics -->
        <div class="row g-4 mb-4">
            <div class="col-lg-3 col-md-6">
                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(74, 111, 165, 0.1); color: var(--primary-color);">
                        <i class="bi bi-joystick"></i>
                    </div>
                    <div class="stat-number" id="gamesPlayed">0</div>
                    <div class="stat-label">Games Played</div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(22, 96, 136, 0.1); color: var(--secondary-color);">
                        <i class="bi bi-trophy"></i>
                    </div>
                    <div class="stat-number" id="gamesWon">0</div>
                    <div class="stat-label">Games Completed</div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(23, 162, 184, 0.1); color: var(--accent-color);">
                        <i class="bi bi-clock-history"></i>
                    </div>
                    <div class="stat-number" id="playTime">0</div>
                    <div class="stat-label">Minutes Played</div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(40, 167, 69, 0.1); color: #28a745;">
                        <i class="bi bi-star"></i>
                    </div>
                    <div class="stat-number" id="skillPoints">0</div>
                    <div class="stat-label">Skill Points</div>
                </div>
            </div>
        </div>
        
        <!-- Games Grid -->
        <div class="row g-4 mb-4">
            <div class="col-lg-4 col-md-6">
                <div class="game-card">
                    <div class="game-header">
                        <div class="game-icon">
                            <i class="fas fa-brain"></i>
                        </div>
                        <h4>Memory Match</h4>
                        <p>Find matching pairs</p>
                    </div>
                    <div class="game-content">
                        <h5 class="game-title">Memory Match Game</h5>
                        <p class="game-description">
                            Test your memory by finding matching pairs of cards. This game helps improve 
                            concentration and visual memory skills.
                        </p>
                        <div class="game-stats">
                            <span class="game-stat">
                                <i class="bi bi-clock"></i> 2-5 min
                            </span>
                            <span class="game-stat">
                                <i class="bi bi-brain"></i> Memory
                            </span>
                            <span class="game-stat">
                                <i class="bi bi-people"></i> 1 Player
                            </span>
                        </div>
                        <a href="#" class="play-btn" onclick="openGame('memory')">
                            <i class="fas fa-play me-2"></i> Play Now
                        </a>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4 col-md-6">
                <div class="game-card">
                    <div class="game-header">
                        <div class="game-icon">
                            <i class="fas fa-times"></i>
                        </div>
                        <h4>Tic Tac Toe</h4>
                        <p>Classic strategy game</p>
                    </div>
                    <div class="game-content">
                        <h5 class="game-title">Tic Tac Toe</h5>
                        <p class="game-description">
                            Play the classic game of X and O against the computer. Develop strategic 
                            thinking and planning skills.
                        </p>
                        <div class="game-stats">
                            <span class="game-stat">
                                <i class="bi bi-clock"></i> 1-3 min
                            </span>
                            <span class="game-stat">
                                <i class="fas fa-chess"></i> Strategy
                            </span>
                            <span class="game-stat">
                                <i class="bi bi-people"></i> 1 Player
                            </span>
                        </div>
                        <a href="#" class="play-btn" onclick="openGame('tictactoe')">
                            <i class="fas fa-play me-2"></i> Play Now
                        </a>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4 col-md-6">
                <div class="game-card">
                    <div class="game-header">
                        <div class="game-icon">
                            <i class="fas fa-calculator"></i>
                        </div>
                        <h4>Math Quiz</h4>
                        <p>Fun with numbers</p>
                    </div>
                    <div class="game-content">
                        <h5 class="game-title">Math Quiz Challenge</h5>
                        <p class="game-description">
                            Solve simple math problems. Perfect for learning basic arithmetic while 
                            having fun with numbers.
                        </p>
                        <div class="game-stats">
                            <span class="game-stat">
                                <i class="bi bi-clock"></i> 3-5 min
                            </span>
                            <span class="game-stat">
                                <i class="fas fa-book"></i> Learning
                            </span>
                            <span class="game-stat">
                                <i class="fas fa-graduation-cap"></i> Age 5+
                            </span>
                        </div>
                        <a href="#" class="play-btn" onclick="openGame('math')">
                            <i class="fas fa-play me-2"></i> Play Now
                        </a>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4 col-md-6">
                <div class="game-card">
                    <div class="game-header">
                        <div class="game-icon">
                            <i class="fas fa-font"></i>
                        </div>
                        <h4>Alphabet Hunt</h4>
                        <p>Find hidden letters</p>
                    </div>
                    <div class="game-content">
                        <h5 class="game-title">Alphabet Hunt</h5>
                        <p class="game-description">
                            Find hidden letters in colorful pictures. Great for learning ABCs and 
                            improving letter recognition.
                        </p>
                        <div class="game-stats">
                            <span class="game-stat">
                                <i class="bi bi-clock"></i> 2-4 min
                            </span>
                            <span class="game-stat">
                                <i class="fas fa-book"></i> Learning
                            </span>
                            <span class="game-stat">
                                <i class="fas fa-graduation-cap"></i> Age 3+
                            </span>
                        </div>
                        <a href="#" class="play-btn" onclick="openGame('alphabet')">
                            <i class="fas fa-play me-2"></i> Play Now
                        </a>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4 col-md-6">
                <div class="game-card">
                    <div class="game-header">
                        <div class="game-icon">
                            <i class="fas fa-paw"></i>
                        </div>
                        <h4>Animal Sounds</h4>
                        <p>Guess the animal</p>
                    </div>
                    <div class="game-content">
                        <h5 class="game-title">Animal Sounds Game</h5>
                        <p class="game-description">
                            Listen to animal sounds and guess which animal makes them! Learn about 
                            different animals in a fun way.
                        </p>
                        <div class="game-stats">
                            <span class="game-stat">
                                <i class="bi bi-clock"></i> 3-5 min
                            </span>
                            <span class="game-stat">
                                <i class="fas fa-ear"></i> Listening
                            </span>
                            <span class="game-stat">
                                <i class="fas fa-graduation-cap"></i> Age 2+
                            </span>
                        </div>
                        <a href="#" class="play-btn" onclick="openGame('animals')">
                            <i class="fas fa-play me-2"></i> Play Now
                        </a>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4 col-md-6">
                <div class="game-card">
                    <div class="game-header">
                        <div class="game-icon">
                            <i class="fas fa-paint-brush"></i>
                        </div>
                        <h4>Drawing Board</h4>
                        <p>Creative drawing</p>
                    </div>
                    <div class="game-content">
                        <h5 class="game-title">Drawing Board</h5>
                        <p class="game-description">
                            Unleash your creativity with our drawing tool. Create beautiful artwork 
                            with different colors and tools.
                        </p>
                        <div class="game-stats">
                            <span class="game-stat">
                                <i class="bi bi-clock"></i> 5-10 min
                            </span>
                            <span class="game-stat">
                                <i class="fas fa-palette"></i> Creative
                            </span>
                            <span class="game-stat">
                                <i class="bi bi-people"></i> 1 Player
                            </span>
                        </div>
                        <a href="#" class="play-btn" onclick="openGame('drawing')">
                            <i class="fas fa-play me-2"></i> Start Drawing
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Quick Actions & Activity -->
        <div class="row g-4">
            <!-- Quick Actions -->
            <div class="col-lg-8">
                <div class="section-card mb-4">
                    <h4 class="section-title">
                        <i class="bi bi-lightning me-2"></i> Quick Actions
                    </h4>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <a href="#" class="quick-action" onclick="startDrawingGame()">
                                <div class="quick-action-icon" style="color: var(--primary-color);">
                                    <i class="fas fa-paint-brush"></i>
                                </div>
                                <div>
                                    <h6 class="mb-1">Start Drawing</h6>
                                    <small class="text-muted">Create beautiful artwork</small>
                                </div>
                            </a>
                        </div>
                        <div class="col-md-6">
                            <a href="#" class="quick-action" onclick="openGame('memory')">
                                <div class="quick-action-icon" style="color: var(--secondary-color);">
                                    <i class="fas fa-brain"></i>
                                </div>
                                <div>
                                    <h6 class="mb-1">Memory Game</h6>
                                    <small class="text-muted">Test your memory skills</small>
                                </div>
                            </a>
                        </div>
                        <div class="col-md-6">
                            <a href="#" class="quick-action" onclick="openGame('math')">
                                <div class="quick-action-icon" style="color: var(--accent-color);">
                                    <i class="fas fa-calculator"></i>
                                </div>
                                <div>
                                    <h6 class="mb-1">Math Practice</h6>
                                    <small class="text-muted">Practice with numbers</small>
                                </div>
                            </a>
                        </div>
                        <div class="col-md-6">
                            <a href="#" class="quick-action" onclick="openGame('alphabet')">
                                <div class="quick-action-icon" style="color: #28a745;">
                                    <i class="fas fa-font"></i>
                                </div>
                                <div>
                                    <h6 class="mb-1">Learn Letters</h6>
                                    <small class="text-muted">Find hidden letters</small>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- Recent Activity -->
                <div class="section-card">
                    <h4 class="section-title">
                        <i class="bi bi-activity me-2"></i> Recent Game Activity
                    </h4>
                    <div class="activity-list" id="gameActivity">
                        <!-- Activity items will be added dynamically -->
                    </div>
                </div>
            </div>
            
            <!-- Quick Tips -->
            <div class="col-lg-4">
                <div class="section-card">
                    <h4 class="section-title">
                        <i class="bi bi-lightbulb me-2"></i> Parent Tips
                    </h4>
                    <div class="alert alert-info border-0 bg-light">
                        <small>
                            <i class="bi bi-info-circle me-2"></i>
                            <strong>Tip:</strong> Play games together with your child for better engagement.
                        </small>
                    </div>
                    <div class="alert alert-success border-0 bg-light">
                        <small>
                            <i class="bi bi-check-circle me-2"></i>
                            <strong>Remember:</strong> Each game is designed to develop specific skills.
                        </small>
                    </div>
                    <div class="alert alert-warning border-0 bg-light">
                        <small>
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            <strong>Note:</strong> Limit screen time to 30 minutes per session.
                        </small>
                    </div>
                    
                    <!-- Skill Progress -->
                    <div class="mt-4">
                        <h6 class="text-primary mb-3">
                            <i class="bi bi-graph-up me-2"></i> Skills Developed
                        </h6>
                        <div class="mb-2">
                            <small class="text-muted d-block">Memory</small>
                            <div class="progress" style="height: 8px;">
                                <div class="progress-bar bg-primary" id="memoryProgress" style="width: 60%;"></div>
                            </div>
                        </div>
                        <div class="mb-2">
                            <small class="text-muted d-block">Problem Solving</small>
                            <div class="progress" style="height: 8px;">
                                <div class="progress-bar bg-success" id="problemProgress" style="width: 45%;"></div>
                            </div>
                        </div>
                        <div class="mb-2">
                            <small class="text-muted d-block">Creativity</small>
                            <div class="progress" style="height: 8px;">
                                <div class="progress-bar bg-warning" id="creativityProgress" style="width: 75%;"></div>
                            </div>
                        </div>
                        <div class="mb-2">
                            <small class="text-muted d-block">Learning</small>
                            <div class="progress" style="height: 8px;">
                                <div class="progress-bar bg-info" id="learningProgress" style="width: 50%;"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Footer -->
    <footer class="bg-dark text-white py-4 mt-5">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <h5>CareGuides Games</h5>
                    <p class="text-light">Educational games for children's development</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <p class="mb-0">&copy; 2024 CareGuides. All rights reserved.</p>
                    <small>Designed with ❤️ for children</small>
                </div>
            </div>
        </div>
    </footer>
    
    <!-- Game Modal -->
    <div class="modal fade" id="gameModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Game</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="modalBody">
                    <!-- Game content will be loaded here -->
                </div>
            </div>
        </div>
    </div>
    
    <!-- Parent Info Modal -->
    <div class="modal fade" id="parentModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-info-circle me-2"></i> For Parents
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <h6 class="text-primary mb-3">About Our Games</h6>
                    <p>These games are designed to help children develop important skills while having fun:</p>
                    
                    <div class="row mt-3">
                        <div class="col-md-6 mb-3">
                            <div class="d-flex align-items-start">
                                <div class="me-3 text-primary">
                                    <i class="bi bi-brain fs-4"></i>
                                </div>
                                <div>
                                    <h6>Memory Skills</h6>
                                    <small class="text-muted">Memory games improve concentration and recall</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="d-flex align-items-start">
                                <div class="me-3 text-success">
                                    <i class="bi bi-lightbulb fs-4"></i>
                                </div>
                                <div>
                                    <h6>Problem Solving</h6>
                                    <small class="text-muted">Strategy games develop logical thinking</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="d-flex align-items-start">
                                <div class="me-3 text-warning">
                                    <i class="bi bi-palette fs-4"></i>
                                </div>
                                <div>
                                    <h6>Creativity</h6>
                                    <small class="text-muted">Drawing games encourage creative expression</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="d-flex align-items-start">
                                <div class="me-3 text-info">
                                    <i class="bi bi-book fs-4"></i>
                                </div>
                                <div>
                                    <h6>Learning</h6>
                                    <small class="text-muted">Educational games reinforce basic concepts</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="alert alert-info mt-3">
                        <small>
                            <i class="bi bi-clock me-2"></i>
                            <strong>Recommended Play Time:</strong> 30 minutes per session
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Game Statistics
        let gamesPlayed = 0;
        let gamesWon = 0;
        let playTime = 0;
        let skillPoints = 0;
        let gameActivity = [];
        
        // Initialize statistics from localStorage
        function initStatistics() {
            const stats = JSON.parse(localStorage.getItem('gameStats')) || {
                gamesPlayed: 0,
                gamesWon: 0,
                playTime: 0,
                skillPoints: 0
            };
            
            gamesPlayed = stats.gamesPlayed;
            gamesWon = stats.gamesWon;
            playTime = stats.playTime;
            skillPoints = stats.skillPoints;
            
            updateStatsDisplay();
            
            // Load game activity
            gameActivity = JSON.parse(localStorage.getItem('gameActivity')) || [];
            updateActivityDisplay();
            
            // Update skill progress
            updateSkillProgress();
        }
        
        // Update statistics display
        function updateStatsDisplay() {
            document.getElementById('gamesPlayed').textContent = gamesPlayed;
            document.getElementById('gamesWon').textContent = gamesWon;
            document.getElementById('playTime').textContent = Math.floor(playTime);
            document.getElementById('skillPoints').textContent = skillPoints;
        }
        
        // Save statistics to localStorage
        function saveStatistics() {
            const stats = {
                gamesPlayed,
                gamesWon,
                playTime,
                skillPoints
            };
            localStorage.setItem('gameStats', JSON.stringify(stats));
            localStorage.setItem('gameActivity', JSON.stringify(gameActivity));
        }
        
        // Add game activity
        function addGameActivity(gameName, result) {
            const activity = {
                game: gameName,
                result: result,
                time: new Date().toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'}),
                date: new Date().toLocaleDateString()
            };
            
            gameActivity.unshift(activity);
            if (gameActivity.length > 5) {
                gameActivity = gameActivity.slice(0, 5);
            }
            
            updateActivityDisplay();
            saveStatistics();
        }
        
        // Update activity display
        function updateActivityDisplay() {
            const container = document.getElementById('gameActivity');
            if (!container) return;
            
            if (gameActivity.length === 0) {
                container.innerHTML = `
                    <div class="empty-state">
                        <div class="empty-state-icon">
                            <i class="bi bi-joystick"></i>
                        </div>
                        <p class="text-muted">No recent activity</p>
                    </div>
                `;
                return;
            }
            
            let html = '';
            gameActivity.forEach(activity => {
                const icon = getGameIcon(activity.game);
                const color = activity.result === 'win' ? 'text-success' : 'text-primary';
                
                html += `
                    <div class="activity-item">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h6 class="mb-1" style="font-size: 0.95rem;">
                                    <i class="${icon} ${color} me-2"></i>
                                    Played ${activity.game}
                                </h6>
                                <small class="text-muted">${activity.result === 'win' ? 'Completed successfully' : 'Game played'}</small>
                            </div>
                            <small class="activity-time">
                                ${activity.date}<br>
                                ${activity.time}
                            </small>
                        </div>
                    </div>
                `;
            });
            
            container.innerHTML = html;
        }
        
        // Get game icon
        function getGameIcon(gameName) {
            const icons = {
                'Memory Match': 'fas fa-brain',
                'Tic Tac Toe': 'fas fa-times',
                'Math Quiz': 'fas fa-calculator',
                'Alphabet Hunt': 'fas fa-font',
                'Animal Sounds': 'fas fa-paw',
                'Drawing Board': 'fas fa-paint-brush'
            };
            return icons[gameName] || 'bi bi-joystick';
        }
        
        // Update skill progress
        function updateSkillProgress() {
            // Calculate progress based on games played
            const memoryProgress = Math.min(100, gamesPlayed * 20);
            const problemProgress = Math.min(100, gamesWon * 25);
            const creativityProgress = Math.min(100, skillPoints * 2);
            const learningProgress = Math.min(100, (gamesPlayed + gamesWon) * 15);
            
            document.getElementById('memoryProgress').style.width = memoryProgress + '%';
            document.getElementById('problemProgress').style.width = problemProgress + '%';
            document.getElementById('creativityProgress').style.width = creativityProgress + '%';
            document.getElementById('learningProgress').style.width = learningProgress + '%';
        }
        
        // Game Functions
        function openGame(gameType) {
            const modal = new bootstrap.Modal(document.getElementById('gameModal'));
            const title = document.getElementById('modalTitle');
            const body = document.getElementById('modalBody');
            
            // Record game start
            gamesPlayed++;
            playTime += 5; // Add 5 minutes for starting a game
            updateStatsDisplay();
            saveStatistics();
            
            switch(gameType) {
                case 'memory':
                    title.textContent = 'Memory Match Game';
                    body.innerHTML = getMemoryGameHTML();
                    setupMemoryGame();
                    break;
                case 'tictactoe':
                    title.textContent = 'Tic Tac Toe';
                    body.innerHTML = getTicTacToeHTML();
                    setupTicTacToe();
                    break;
                case 'math':
                    title.textContent = 'Math Quiz';
                    body.innerHTML = getMathGameHTML();
                    setupMathGame();
                    break;
                case 'alphabet':
                    title.textContent = 'Alphabet Hunt';
                    body.innerHTML = getAlphabetGameHTML();
                    setupAlphabetGame();
                    break;
                case 'animals':
                    title.textContent = 'Animal Sounds';
                    body.innerHTML = getAnimalGameHTML();
                    setupAnimalGame();
                    break;
                case 'drawing':
                    title.textContent = 'Drawing Board';
                    body.innerHTML = getDrawingGameHTML();
                    setupDrawingGame();
                    break;
            }
            
            modal.show();
        }
        
        function showParentInfo() {
            const modal = new bootstrap.Modal(document.getElementById('parentModal'));
            modal.show();
        }
        
        function startDrawingGame() {
            openGame('drawing');
        }
        
        function showAllGames() {
            document.querySelector('.games-section').scrollIntoView({ behavior: 'smooth' });
        }
        
        // Game HTML Templates
        function getMemoryGameHTML() {
            return `
                <div style="text-align: center;">
                    <p>Find matching pairs of cards! Click on two cards to see if they match.</p>
                    <div id="memoryGame" style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; margin: 20px 0; justify-content: center;"></div>
                    <div style="margin: 20px 0;">
                        <p>Matches Found: <span id="matches">0</span>/8</p>
                        <p>Moves: <span id="moves">0</span></p>
                    </div>
                    <button class="btn btn-primary" onclick="resetMemoryGame()">
                        <i class="fas fa-redo me-2"></i> Restart Game
                    </button>
                </div>
            `;
        }
        
        function getTicTacToeHTML() {
            return `
                <div style="text-align: center;">
                    <p>Play against the computer! Get three in a row to win.</p>
                    <div id="ticTacToe" style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 5px; width: 255px; margin: 20px auto;"></div>
                    <div style="margin: 20px 0;">
                        <p>Player: <span id="playerScore">0</span> | Computer: <span id="computerScore">0</span></p>
                        <p id="gameStatus" class="text-primary">Your turn! (X)</p>
                    </div>
                    <button class="btn btn-primary" onclick="resetTicTacToe()">
                        <i class="fas fa-redo me-2"></i> New Game
                    </button>
                </div>
            `;
        }
        
        function getMathGameHTML() {
            return `
                <div style="text-align: center;">
                    <p>Solve the math problem! Choose the correct answer.</p>
                    <div id="mathGame" style="margin: 20px 0;">
                        <div class="math-problem" id="mathProblem"></div>
                        <div id="mathOptions" style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px; margin: 20px 0;"></div>
                    </div>
                    <div style="margin: 20px 0;">
                        <p>Score: <span id="mathScore">0</span> | Correct: <span id="mathCorrect">0</span>/5</p>
                    </div>
                    <button class="btn btn-primary" onclick="nextMathProblem()">
                        <i class="fas fa-forward me-2"></i> Next Problem
                    </button>
                </div>
            `;
        }
        
        function getAlphabetGameHTML() {
            return `
                <div style="text-align: center;">
                    <p>Find the hidden letters in the picture! Click on them when you see them.</p>
                    <div class="alphabet-canvas" id="alphabetCanvas">
                        <div id="alphabetLetters"></div>
                    </div>
                    <div style="margin: 20px 0;">
                        <p>Letters Found: <span id="lettersFound">0</span>/5</p>
                        <p>Find: <span id="targetLetter" style="font-size: 1.5rem; font-weight: bold; color: var(--primary-color);">A</span></p>
                    </div>
                    <button class="btn btn-primary" onclick="newAlphabetGame()">
                        <i class="fas fa-redo me-2"></i> New Game
                    </button>
                </div>
            `;
        }
        
        function getAnimalGameHTML() {
            return `
                <div style="text-align: center;">
                    <p>Listen to the animal sound and guess which animal it is!</p>
                    <div id="animalGame" style="margin: 20px 0;">
                        <div style="margin: 20px 0;">
                            <button class="animal-sound-btn" onclick="playAnimalSound()" id="playSoundBtn">
                                <i class="fas fa-volume-up me-2"></i> Play Sound
                            </button>
                        </div>
                        <div id="animalOptions" style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px; margin: 20px 0;"></div>
                    </div>
                    <div style="margin: 20px 0;">
                        <p>Score: <span id="animalScore">0</span> | Animals Guessed: <span id="animalsGuessed">0</span>/5</p>
                    </div>
                    <button class="btn btn-primary" onclick="newAnimalGame()">
                        <i class="fas fa-redo me-2"></i> Next Animal
                    </button>
                </div>
            `;
        }
        
        function getDrawingGameHTML() {
            return `
                <div style="text-align: center;">
                    <div class="drawing-challenge">
                        <h5>Draw: <span id="drawChallenge" style="color: var(--primary-color);">A House</span></h5>
                        <p class="text-muted">Use your imagination and draw the object!</p>
                    </div>
                    
                    <div class="canvas-container">
                        <canvas id="drawingCanvas" width="600" height="400"></canvas>
                    </div>
                    
                    <div class="drawing-tools">
                        <button class="tool-btn active" data-tool="brush" title="Brush">
                            <i class="fas fa-paint-brush"></i>
                        </button>
                        <button class="tool-btn" data-tool="eraser" title="Eraser">
                            <i class="fas fa-eraser"></i>
                        </button>
                        <button class="tool-btn" data-tool="clear" title="Clear">
                            <i class="fas fa-trash"></i>
                        </button>
                        
                        <div class="color-preview" id="colorPreview" style="background: #3498db;"></div>
                        <input type="color" id="colorPicker" value="#3498db" style="display: none;">
                        
                        <div class="color-palette">
                            <div class="color-swatch" style="background: #3498db;" data-color="#3498db"></div>
                            <div class="color-swatch" style="background: #e74c3c;" data-color="#e74c3c"></div>
                            <div class="color-swatch" style="background: #2ecc71;" data-color="#2ecc71"></div>
                            <div class="color-swatch" style="background: #f39c12;" data-color="#f39c12"></div>
                            <div class="color-swatch" style="background: #9b59b6;" data-color="#9b59b6"></div>
                            <div class="color-swatch" style="background: #000000;" data-color="#000000"></div>
                        </div>
                    </div>
                    
                    <div style="margin: 20px 0;">
                        <div class="d-flex justify-content-center gap-2">
                            <button class="btn btn-success" onclick="submitDrawing()">
                                <i class="fas fa-check me-2"></i> Submit Drawing
                            </button>
                            <button class="btn btn-primary" onclick="newDrawingChallenge()">
                                <i class="fas fa-redo me-2"></i> New Challenge
                            </button>
                        </div>
                    </div>
                </div>
            `;
        }
        
        // Memory Game Implementation
        function setupMemoryGame() {
            const gameDiv = document.getElementById('memoryGame');
            const symbols = ['🐶', '🐱', '🐭', '🐹', '🐰', '🦊', '🐻', '🐼'];
            const cards = [...symbols, ...symbols];
            
            // Shuffle cards
            for (let i = cards.length - 1; i > 0; i--) {
                const j = Math.floor(Math.random() * (i + 1));
                [cards[i], cards[j]] = [cards[j], cards[i]];
            }
            
            let flippedCards = [];
            let matchedPairs = 0;
            let moves = 0;
            let canFlip = true;
            
            gameDiv.innerHTML = '';
            
            cards.forEach((symbol, index) => {
                const card = document.createElement('div');
                card.className = 'memory-card pulse-animation';
                card.innerHTML = '?';
                card.dataset.index = index;
                card.dataset.symbol = symbol;
                
                card.addEventListener('click', () => {
                    if (!canFlip || card.classList.contains('flipped') || card.classList.contains('matched')) return;
                    
                    card.classList.add('flipped');
                    card.innerHTML = symbol;
                    flippedCards.push(card);
                    
                    if (flippedCards.length === 2) {
                        canFlip = false;
                        moves++;
                        document.getElementById('moves').textContent = moves;
                        
                        if (flippedCards[0].dataset.symbol === flippedCards[1].dataset.symbol) {
                            flippedCards.forEach(card => {
                                card.classList.add('matched');
                                card.classList.remove('pulse-animation');
                            });
                            matchedPairs++;
                            document.getElementById('matches').textContent = matchedPairs;
                            flippedCards = [];
                            canFlip = true;
                            
                            if (matchedPairs === symbols.length) {
                                setTimeout(() => {
                                    alert(`🎉 Congratulations! You won in ${moves} moves!`);
                                    gamesWon++;
                                    skillPoints += 10;
                                    updateStatsDisplay();
                                    updateSkillProgress();
                                    saveStatistics();
                                    addGameActivity('Memory Match', 'win');
                                }, 500);
                            }
                        } else {
                            setTimeout(() => {
                                flippedCards.forEach(card => {
                                    card.classList.remove('flipped');
                                    card.innerHTML = '?';
                                });
                                flippedCards = [];
                                canFlip = true;
                            }, 1000);
                        }
                    }
                });
                
                gameDiv.appendChild(card);
            });
            
            // Add game activity
            addGameActivity('Memory Match', 'started');
        }
        
        function resetMemoryGame() {
            setupMemoryGame();
            document.getElementById('matches').textContent = '0';
            document.getElementById('moves').textContent = '0';
        }
        
        // Tic Tac Toe Implementation
        function setupTicTacToe() {
            const gameDiv = document.getElementById('ticTacToe');
            let board = ['', '', '', '', '', '', '', '', ''];
            let currentPlayer = 'X';
            let gameActive = true;
            let playerScore = 0;
            let computerScore = 0;
            
            gameDiv.innerHTML = '';
            
            for (let i = 0; i < 9; i++) {
                const cell = document.createElement('div');
                cell.className = 'tictactoe-cell';
                cell.dataset.index = i;
                
                cell.addEventListener('click', () => {
                    if (board[i] === '' && gameActive && currentPlayer === 'X') {
                        board[i] = 'X';
                        cell.textContent = 'X';
                        cell.style.color = 'var(--primary-color)';
                        
                        if (checkWinner(board, 'X')) {
                            document.getElementById('gameStatus').textContent = 'You Win! 🎉';
                            document.getElementById('gameStatus').className = 'text-success';
                            gameActive = false;
                            playerScore++;
                            document.getElementById('playerScore').textContent = playerScore;
                            
                            gamesWon++;
                            skillPoints += 15;
                            updateStatsDisplay();
                            updateSkillProgress();
                            saveStatistics();
                            addGameActivity('Tic Tac Toe', 'win');
                        } else if (board.every(cell => cell !== '')) {
                            document.getElementById('gameStatus').textContent = "It's a Tie!";
                            document.getElementById('gameStatus').className = 'text-warning';
                            gameActive = false;
                            skillPoints += 5;
                            saveStatistics();
                            addGameActivity('Tic Tac Toe', 'played');
                        } else {
                            currentPlayer = 'O';
                            document.getElementById('gameStatus').textContent = "Computer's turn...";
                            document.getElementById('gameStatus').className = 'text-secondary';
                            
                            // Computer move
                            setTimeout(() => {
                                makeComputerMove();
                            }, 500);
                        }
                    }
                });
                
                gameDiv.appendChild(cell);
            }
            
            function makeComputerMove() {
                if (!gameActive) return;
                
                // Simple AI: Find empty cell
                const emptyCells = board.map((cell, index) => cell === '' ? index : -1).filter(index => index !== -1);
                if (emptyCells.length === 0) return;
                
                const randomIndex = Math.floor(Math.random() * emptyCells.length);
                const computerIndex = emptyCells[randomIndex];
                
                board[computerIndex] = 'O';
                const cell = document.querySelector(`.tictactoe-cell[data-index="${computerIndex}"]`);
                cell.textContent = 'O';
                cell.style.color = 'var(--danger-color)';
                
                if (checkWinner(board, 'O')) {
                    document.getElementById('gameStatus').textContent = 'Computer Wins!';
                    document.getElementById('gameStatus').className = 'text-danger';
                    gameActive = false;
                    computerScore++;
                    document.getElementById('computerScore').textContent = computerScore;
                    addGameActivity('Tic Tac Toe', 'played');
                } else if (board.every(cell => cell !== '')) {
                    document.getElementById('gameStatus').textContent = "It's a Tie!";
                    document.getElementById('gameStatus').className = 'text-warning';
                    gameActive = false;
                    skillPoints += 5;
                    saveStatistics();
                    addGameActivity('Tic Tac Toe', 'played');
                } else {
                    currentPlayer = 'X';
                    document.getElementById('gameStatus').textContent = 'Your turn! (X)';
                    document.getElementById('gameStatus').className = 'text-primary';
                }
            }
            
            function checkWinner(board, player) {
                const winPatterns = [
                    [0,1,2], [3,4,5], [6,7,8], // rows
                    [0,3,6], [1,4,7], [2,5,8], // columns
                    [0,4,8], [2,4,6] // diagonals
                ];
                
                return winPatterns.some(pattern => 
                    pattern.every(index => board[index] === player)
                );
            }
            
            addGameActivity('Tic Tac Toe', 'started');
        }
        
        function resetTicTacToe() {
            setupTicTacToe();
            document.getElementById('playerScore').textContent = '0';
            document.getElementById('computerScore').textContent = '0';
            document.getElementById('gameStatus').textContent = 'Your turn! (X)';
            document.getElementById('gameStatus').className = 'text-primary';
        }
        
        // Math Game Implementation
        function setupMathGame() {
            let score = 0;
            let correctAnswers = 0;
            window.mathGame = { score, correctAnswers };
            nextMathProblem();
            addGameActivity('Math Quiz', 'started');
        }
        
        function nextMathProblem() {
            const num1 = Math.floor(Math.random() * 10) + 1;
            const num2 = Math.floor(Math.random() * 10) + 1;
            const operators = ['+', '-', '×'];
            const operator = operators[Math.floor(Math.random() * operators.length)];
            
            let correctAnswer;
            switch(operator) {
                case '+': correctAnswer = num1 + num2; break;
                case '-': correctAnswer = num1 - num2; break;
                case '×': correctAnswer = num1 * num2; break;
            }
            
            document.getElementById('mathProblem').textContent = `${num1} ${operator} ${num2} = ?`;
            window.currentCorrectAnswer = correctAnswer;
            
            const options = [correctAnswer];
            while (options.length < 4) {
                const option = correctAnswer + Math.floor(Math.random() * 5) - 2;
                if (option !== correctAnswer && !options.includes(option)) {
                    options.push(option);
                }
            }
            
            // Shuffle options
            options.sort(() => Math.random() - 0.5);
            
            const optionsDiv = document.getElementById('mathOptions');
            optionsDiv.innerHTML = '';
            
            options.forEach(option => {
                const btn = document.createElement('div');
                btn.className = 'math-option';
                btn.textContent = option;
                btn.onclick = () => {
                    if (option === correctAnswer) {
                        btn.classList.add('correct');
                        window.mathGame.score += 10;
                        window.mathGame.correctAnswers++;
                        skillPoints += 5;
                        
                        if (window.mathGame.correctAnswers >= 5) {
                            gamesWon++;
                            addGameActivity('Math Quiz', 'win');
                            setTimeout(() => {
                                alert(`🎉 Great job! You scored ${window.mathGame.score} points!`);
                            }, 500);
                        }
                    } else {
                        btn.classList.add('incorrect');
                        // Highlight correct answer
                        document.querySelectorAll('.math-option').forEach(opt => {
                            if (parseInt(opt.textContent) === correctAnswer) {
                                opt.classList.add('correct');
                            }
                        });
                    }
                    
                    document.getElementById('mathScore').textContent = window.mathGame.score;
                    document.getElementById('mathCorrect').textContent = window.mathGame.correctAnswers;
                    
                    // Disable all options
                    document.querySelectorAll('.math-option').forEach(opt => {
                        opt.style.pointerEvents = 'none';
                    });
                    
                    updateStatsDisplay();
                    saveStatistics();
                };
                optionsDiv.appendChild(btn);
            });
        }
        
        // Alphabet Game Implementation
        function setupAlphabetGame() {
            const canvas = document.getElementById('alphabetCanvas');
            const letters = ['A', 'B', 'C', 'D', 'E'];
            let foundCount = 0;
            
            // Clear canvas
            const lettersDiv = document.getElementById('alphabetLetters');
            lettersDiv.innerHTML = '';
            
            // Place letters randomly
            letters.forEach((letter, index) => {
                const x = Math.random() * 250 + 20;
                const y = Math.random() * 150 + 20;
                
                const letterDiv = document.createElement('div');
                letterDiv.className = 'alphabet-letter pulse-animation';
                letterDiv.textContent = '?';
                letterDiv.dataset.letter = letter;
                letterDiv.style.left = x + 'px';
                letterDiv.style.top = y + 'px';
                
                letterDiv.addEventListener('click', () => {
                    if (letterDiv.textContent === '?') {
                        letterDiv.textContent = letter;
                        letterDiv.classList.add('found');
                        letterDiv.classList.remove('pulse-animation');
                        foundCount++;
                        document.getElementById('lettersFound').textContent = foundCount;
                        
                        if (foundCount === letters.length) {
                            setTimeout(() => {
                                alert(`🎉 Congratulations! You found all the letters!`);
                                gamesWon++;
                                skillPoints += 10;
                                updateStatsDisplay();
                                updateSkillProgress();
                                saveStatistics();
                                addGameActivity('Alphabet Hunt', 'win');
                            }, 500);
                        }
                    }
                });
                
                lettersDiv.appendChild(letterDiv);
            });
            
            document.getElementById('targetLetter').textContent = letters.join(', ');
            addGameActivity('Alphabet Hunt', 'started');
        }
        
        function newAlphabetGame() {
            setupAlphabetGame();
            document.getElementById('lettersFound').textContent = '0';
        }
        
        // Animal Game Implementation
        function setupAnimalGame() {
            const animals = [
                {name: 'Cat', sound: 'meow', emoji: '🐱'},
                {name: 'Dog', sound: 'woof', emoji: '🐶'},
                {name: 'Cow', sound: 'moo', emoji: '🐮'},
                {name: 'Sheep', sound: 'baa', emoji: '🐑'},
                {name: 'Lion', sound: 'roar', emoji: '🦁'}
            ];
            
            const currentAnimal = animals[Math.floor(Math.random() * animals.length)];
            window.currentAnimal = currentAnimal;
            
            const optionsDiv = document.getElementById('animalOptions');
            optionsDiv.innerHTML = '';
            
            const allAnimals = [...animals].sort(() => Math.random() - 0.5).slice(0, 4);
            if (!allAnimals.includes(currentAnimal)) {
                allAnimals[3] = currentAnimal;
            }
            
            allAnimals.forEach(animal => {
                const btn = document.createElement('div');
                btn.className = 'animal-option';
                btn.innerHTML = `${animal.emoji} ${animal.name}`;
                btn.onclick = () => {
                    if (animal.name === currentAnimal.name) {
                        btn.classList.add('correct');
                        const score = parseInt(document.getElementById('animalScore').textContent);
                        document.getElementById('animalScore').textContent = score + 10;
                        const guessed = parseInt(document.getElementById('animalsGuessed').textContent);
                        document.getElementById('animalsGuessed').textContent = guessed + 1;
                        
                        skillPoints += 5;
                        
                        if (guessed + 1 >= 5) {
                            gamesWon++;
                            addGameActivity('Animal Sounds', 'win');
                            setTimeout(() => {
                                alert(`🎉 Great job! You guessed all animals!`);
                            }, 500);
                        }
                    } else {
                        btn.classList.add('incorrect');
                    }
                    
                    // Disable all options
                    document.querySelectorAll('.animal-option').forEach(b => {
                        b.style.pointerEvents = 'none';
                    });
                    
                    updateStatsDisplay();
                    saveStatistics();
                };
                optionsDiv.appendChild(btn);
            });
            
            addGameActivity('Animal Sounds', 'started');
        }
        
        function playAnimalSound() {
            const btn = document.getElementById('playSoundBtn');
            btn.innerHTML = '<i class="fas fa-volume-up me-2"></i> Playing sound...';
            btn.disabled = true;
            
            setTimeout(() => {
                btn.innerHTML = `<i class="fas fa-volume-up me-2"></i> Sound played! It's a ${window.currentAnimal.name}`;
                setTimeout(() => {
                    btn.innerHTML = '<i class="fas fa-volume-up me-2"></i> Play Sound Again';
                    btn.disabled = false;
                }, 2000);
            }, 1000);
        }
        
        function newAnimalGame() {
            setupAnimalGame();
            document.getElementById('playSoundBtn').innerHTML = '<i class="fas fa-volume-up me-2"></i> Play Sound';
            document.getElementById('playSoundBtn').disabled = false;
        }
        
        // Drawing Game Implementation
        function setupDrawingGame() {
            const canvas = document.getElementById('drawingCanvas');
            const ctx = canvas.getContext('2d');
            let drawing = false;
            let currentTool = 'brush';
            let currentColor = '#3498db';
            let brushSize = 5;
            
            // Clear canvas
            ctx.fillStyle = 'white';
            ctx.fillRect(0, 0, canvas.width, canvas.height);
            
            // Set up drawing
            canvas.addEventListener('mousedown', (e) => {
                drawing = true;
                const rect = canvas.getBoundingClientRect();
                ctx.beginPath();
                ctx.moveTo(e.clientX - rect.left, e.clientY - rect.top);
            });
            
            canvas.addEventListener('mousemove', (e) => {
                if (!drawing) return;
                const rect = canvas.getBoundingClientRect();
                ctx.lineTo(e.clientX - rect.left, e.clientY - rect.top);
                ctx.strokeStyle = currentColor;
                ctx.lineWidth = brushSize;
                ctx.lineCap = 'round';
                ctx.lineJoin = 'round';
                ctx.stroke();
            });
            
            canvas.addEventListener('mouseup', () => {
                drawing = false;
            });
            
            // Set random challenge
            const challenges = ['A House', 'A Tree', 'A Sun', 'A Flower', 'A Car', 'A Cat', 'A Boat', 'A Rainbow'];
            const randomChallenge = challenges[Math.floor(Math.random() * challenges.length)];
            document.getElementById('drawChallenge').textContent = randomChallenge;
            
            // Tool selection
            document.querySelectorAll('.tool-btn').forEach(btn => {
                btn.addEventListener('click', () => {
                    document.querySelectorAll('.tool-btn').forEach(b => b.classList.remove('active'));
                    btn.classList.add('active');
                    currentTool = btn.dataset.tool;
                    
                    if (currentTool === 'clear') {
                        ctx.fillStyle = 'white';
                        ctx.fillRect(0, 0, canvas.width, canvas.height);
                    } else if (currentTool === 'eraser') {
                        currentColor = 'white';
                        brushSize = 20;
                    }
                });
            });
            
            // Color selection
            document.getElementById('colorPicker').addEventListener('input', (e) => {
                currentColor = e.target.value;
                document.getElementById('colorPreview').style.backgroundColor = currentColor;
                currentTool = 'brush';
                document.querySelectorAll('.tool-btn').forEach(b => {
                    b.classList.remove('active');
                    if (b.dataset.tool === 'brush') b.classList.add('active');
                });
            });
            
            document.getElementById('colorPreview').addEventListener('click', () => {
                document.getElementById('colorPicker').click();
            });
            
            document.querySelectorAll('.color-swatch').forEach(swatch => {
                swatch.addEventListener('click', () => {
                    currentColor = swatch.dataset.color;
                    document.getElementById('colorPreview').style.backgroundColor = currentColor;
                    document.getElementById('colorPicker').value = currentColor;
                    currentTool = 'brush';
                    document.querySelectorAll('.tool-btn').forEach(b => {
                        b.classList.remove('active');
                        if (b.dataset.tool === 'brush') b.classList.add('active');
                    });
                });
            });
            
            addGameActivity('Drawing Board', 'started');
        }
        
        function newDrawingChallenge() {
            setupDrawingGame();
        }
        
        function submitDrawing() {
            skillPoints += 15;
            gamesWon++;
            updateStatsDisplay();
            updateSkillProgress();
            saveStatistics();
            addGameActivity('Drawing Board', 'win');
            alert('🎨 Great drawing! You earned 15 skill points!');
        }
        
        // Initialize everything when page loads
        document.addEventListener('DOMContentLoaded', () => {
            initStatistics();
            
            // Update local time
            function updateLocalTime() {
                const now = new Date();
                const timeElements = document.querySelectorAll('.current-time');
                if (timeElements.length > 0) {
                    timeElements.forEach(el => {
                        el.textContent = now.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
                    });
                }
            }
            
            setInterval(updateLocalTime, 60000);
            updateLocalTime();
            
            // Animate stat cards
            setTimeout(() => {
                const cards = document.querySelectorAll('.stat-card');
                cards.forEach((card, index) => {
                    setTimeout(() => {
                        card.style.opacity = '1';
                        card.style.transform = 'translateY(0)';
                    }, index * 100);
                });
            }, 300);
        });
    </script>
</body>
</html>