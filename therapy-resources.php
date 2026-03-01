<?php
session_start();
require_once 'config/database.php';
requireLogin();

$userId = $_SESSION['user_id'];
$therapyType = $_GET['therapy'] ?? '';
$category = $_GET['category'] ?? 'all';

// Get user's latest test to recommend relevant therapy
$latestTestQuery = $conn->prepare("
    SELECT * FROM test_results 
    WHERE user_id = ? 
    ORDER BY test_date DESC 
    LIMIT 1
");

if ($latestTestQuery) {
    $latestTestQuery->bind_param("i", $userId);
    $latestTestQuery->execute();
    $latestTest = $latestTestQuery->get_result();
} else {
    $latestTest = false;
    error_log("Error preparing latest test query: " . $conn->error);
}

$recommendedTherapy = null;
if ($latestTest && $latestTest->num_rows > 0) {
    $testData = $latestTest->fetch_assoc();
    $recommendedTherapy = $testData['therapy_prediction'] ?? null;
}

// Define therapy resources
$therapyResources = [
    'Speech' => [
        'title' => 'Speech Therapy Resources',
        'description' => 'Resources for improving communication, language development, and speech clarity.',
        'color' => '#10b981',
        'icon' => 'bi-chat-dots',
        'resources' => [
            [
                'title' => 'Speech Therapy Exercises for Children',
                'description' => 'Simple daily exercises to improve articulation and language skills.',
                'type' => 'pdf',
                'url' => '#',
                'duration' => '15 min'
            ],
            [
                'title' => 'Building Vocabulary Through Play',
                'description' => 'Fun games and activities to expand your child\'s vocabulary.',
                'type' => 'video',
                'url' => '#',
                'duration' => '10 min'
            ],
            [
                'title' => 'Communication Board Templates',
                'description' => 'Printable communication boards for non-verbal children.',
                'type' => 'pdf',
                'url' => '#',
                'duration' => 'Print'
            ],
            [
                'title' => 'Speech Therapist Directory',
                'description' => 'Find certified speech therapists in your area.',
                'type' => 'directory',
                'url' => '#',
                'duration' => 'Search'
            ]
        ]
    ],
    'OT' => [
        'title' => 'Occupational Therapy Resources',
        'description' => 'Resources for developing daily living skills, sensory processing, and fine motor coordination.',
        'color' => '#8b5cf6',
        'icon' => 'bi-hand-index-thumb',
        'resources' => [
            [
                'title' => 'Fine Motor Skills Activities',
                'description' => 'Activities to improve hand-eye coordination and dexterity.',
                'type' => 'pdf',
                'url' => '#',
                'duration' => '20 min'
            ],
            [
                'title' => 'Sensory Diet Guide',
                'description' => 'Creating a daily sensory schedule for your child.',
                'type' => 'pdf',
                'url' => '#',
                'duration' => 'Guide'
            ],
            [
                'title' => 'Daily Living Skills Checklist',
                'description' => 'Track progress on self-care and independence skills.',
                'type' => 'checklist',
                'url' => '#',
                'duration' => 'PDF'
            ]
        ]
    ],
    'ABA' => [
        'title' => 'ABA Therapy Resources',
        'description' => 'Resources for Applied Behavior Analysis techniques and strategies.',
        'color' => '#ef4444',
        'icon' => 'bi-diagram-3',
        'resources' => [
            [
                'title' => 'ABA Techniques for Home',
                'description' => 'Simple ABA strategies parents can implement at home.',
                'type' => 'video',
                'url' => '#',
                'duration' => '25 min'
            ],
            [
                'title' => 'Behavior Tracking Sheets',
                'description' => 'Templates for tracking behaviors and interventions.',
                'type' => 'pdf',
                'url' => '#',
                'duration' => 'Print'
            ],
            [
                'title' => 'Positive Reinforcement Guide',
                'description' => 'How to effectively use positive reinforcement.',
                'type' => 'pdf',
                'url' => '#',
                'duration' => 'Guide'
            ]
        ]
    ],
    'Play' => [
        'title' => 'Play Therapy Resources',
        'description' => 'Resources for using play to improve social, emotional, and cognitive development.',
        'color' => '#f59e0b',
        'icon' => 'bi-joystick',
        'resources' => [
            [
                'title' => 'Therapeutic Play Activities',
                'description' => 'Structured play activities with therapeutic benefits.',
                'type' => 'pdf',
                'url' => '#',
                'duration' => '30 min'
            ],
            [
                'title' => 'Social Skills Games',
                'description' => 'Fun games that teach turn-taking and cooperation.',
                'type' => 'video',
                'url' => '#',
                'duration' => '15 min'
            ],
            [
                'title' => 'Emotional Expression Through Play',
                'description' => 'Helping children express emotions through play.',
                'type' => 'pdf',
                'url' => '#',
                'duration' => 'Guide'
            ]
        ]
    ]
];

// If a specific therapy is selected, filter resources
$filteredResources = [];
if ($therapyType && isset($therapyResources[$therapyType])) {
    $filteredResources[$therapyType] = $therapyResources[$therapyType];
} else {
    $filteredResources = $therapyResources;
}

// Get user's bookmarked resources - FIXED: Check if table exists first
$bookmarkedIds = [];

// Check if the bookmarks table exists
$tableCheck = $conn->query("SHOW TABLES LIKE 'user_resource_bookmarks'");
if ($tableCheck && $tableCheck->num_rows > 0) {
    // Table exists, prepare the query
    $bookmarksQuery = $conn->prepare("
        SELECT resource_id FROM user_resource_bookmarks WHERE user_id = ?
    ");
    
    if ($bookmarksQuery) {
        $bookmarksQuery->bind_param("i", $userId);
        $bookmarksQuery->execute();
        $bookmarksResult = $bookmarksQuery->get_result();
        
        while ($row = $bookmarksResult->fetch_assoc()) {
            $bookmarkedIds[] = $row['resource_id'];
        }
    } else {
        error_log("Error preparing bookmarks query: " . $conn->error);
    }
} else {
    // Table doesn't exist, create it or use empty array
    error_log("Table user_resource_bookmarks doesn't exist. Bookmarks feature disabled.");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Therapy Resources - CareGuides</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        body {
            background: #f5f7fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .page-header {
            background: linear-gradient(135deg, #4a6fa5, #166088);
            color: #fff;
            padding: 40px 0;
            margin-bottom: 30px;
        }
        
        .therapy-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            margin-bottom: 30px;
            transition: all 0.3s;
            border-top: 4px solid;
        }
        
        .therapy-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.12);
        }
        
        .therapy-header {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .therapy-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            margin-right: 15px;
            color: white;
        }
        
        .therapy-title {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .therapy-description {
            color: #666;
            font-size: 0.95rem;
        }
        
        .resource-item {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            border-left: 4px solid #dee2e6;
            transition: all 0.3s;
        }
        
        .resource-item:hover {
            background: #e9ecef;
            border-left-color: #4a6fa5;
        }
        
        .resource-type-badge {
            padding: 4px 10px;
            border-radius: 15px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .resource-type-pdf {
            background: #fee2e2;
            color: #dc2626;
        }
        
        .resource-type-video {
            background: #dbeafe;
            color: #1d4ed8;
        }
        
        .resource-type-directory {
            background: #dcfce7;
            color: #16a34a;
        }
        
        .resource-type-checklist {
            background: #fef3c7;
            color: #d97706;
        }
        
        .resource-duration {
            font-size: 0.85rem;
            color: #6c757d;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .bookmark-btn {
            background: none;
            border: none;
            color: #dee2e6;
            font-size: 1.2rem;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .bookmark-btn.active {
            color: #ffc107;
        }
        
        .bookmark-btn:hover {
            color: #ffc107;
        }
        
        .recommended-badge {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .category-filter {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 25px;
        }
        
        .category-btn {
            padding: 8px 20px;
            border-radius: 20px;
            border: 2px solid #dee2e6;
            background: white;
            color: #495057;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .category-btn:hover,
        .category-btn.active {
            border-color: #4a6fa5;
            background: #4a6fa5;
            color: white;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
        }
        
        .empty-state-icon {
            font-size: 4rem;
            color: #dee2e6;
            margin-bottom: 20px;
        }
        
        .quick-links {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.08);
            margin-bottom: 30px;
        }
        
        .quick-link-item {
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
        
        .quick-link-item:hover {
            background: #e9ecef;
            transform: translateX(5px);
            text-decoration: none;
            color: #333;
        }
        
        .quick-link-icon {
            width: 50px;
            height: 50px;
            background: #e0f2fe;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            font-size: 20px;
            color: #0369a1;
        }
        
        .therapy-tip {
            background: linear-gradient(135deg, #f0f9ff, #e0f2fe);
            border-radius: 10px;
            padding: 20px;
            margin-top: 20px;
            border: 1px solid #bae6fd;
        }
        
        @media (max-width: 768px) {
            .therapy-header {
                flex-direction: column;
                text-align: center;
            }
            
            .therapy-icon {
                margin-right: 0;
                margin-bottom: 15px;
            }
            
            .category-filter {
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <!-- Page Header -->
    <div class="page-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="display-5 fw-bold">
                        <i class="bi bi-heart-pulse me-3"></i>Therapy Resources
                    </h1>
                    <p class="lead mb-0">Evidence-based resources and materials for different therapy approaches</p>
                </div>
                <div class="col-md-4 text-md-end">
                    <?php if ($recommendedTherapy): ?>
                    <div class="recommended-badge">
                        <i class="bi bi-stars me-1"></i>
                        Recommended: <?php echo $recommendedTherapy; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Quick Links -->
    <div class="container mb-4">
        <div class="quick-links">
            <h4 class="mb-4"><i class="bi bi-lightning me-2"></i> Quick Access</h4>
            <div class="row g-3">
                <div class="col-md-3">
                    <a href="find-therapist.php" class="quick-link-item">
                        <div class="quick-link-icon">
                            <i class="bi bi-person-badge"></i>
                        </div>
                        <div>
                            <h6 class="mb-1">Find Therapists</h6>
                            <small class="text-muted">Certified specialists</small>
                        </div>
                    </a>
                </div>
                <div class="col-md-3">
                    <a href="appointments.php" class="quick-link-item">
                        <div class="quick-link-icon">
                            <i class="bi bi-calendar-check"></i>
                        </div>
                        <div>
                            <h6 class="mb-1">My Appointments</h6>
                            <small class="text-muted">Schedule management</small>
                        </div>
                    </a>
                </div>
                <div class="col-md-3">
                    <a href="reports.php" class="quick-link-item">
                        <div class="quick-link-icon">
                            <i class="bi bi-file-earmark-medical"></i>
                        </div>
                        <div>
                            <h6 class="mb-1">Generate Reports</h6>
                            <small class="text-muted">Progress tracking</small>
                        </div>
                    </a>
                </div>
                <div class="col-md-3">
                    <a href="test-history.php" class="quick-link-item">
                        <div class="quick-link-icon">
                            <i class="bi bi-clipboard-data"></i>
                        </div>
                        <div>
                            <h6 class="mb-1">Test History</h6>
                            <small class="text-muted">Review past tests</small>
                        </div>
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Category Filter -->
    <div class="container mb-4">
        <div class="category-filter">
            <a href="therapy-resources.php" 
               class="category-btn <?php echo empty($therapyType) ? 'active' : ''; ?>">
                All Therapies
            </a>
            <?php foreach ($therapyResources as $type => $therapy): ?>
            <a href="therapy-resources.php?therapy=<?php echo urlencode($type); ?>" 
               class="category-btn <?php echo $therapyType == $type ? 'active' : ''; ?>"
               style="border-color: <?php echo $therapy['color']; ?>;">
                <i class="bi <?php echo $therapy['icon']; ?> me-2"></i>
                <?php echo $type; ?>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
    
    <!-- Resources Content -->
    <div class="container pb-5">
        <?php if (!empty($filteredResources)): ?>
            <?php foreach ($filteredResources as $type => $therapy): ?>
            <div class="therapy-card" style="border-top-color: <?php echo $therapy['color']; ?>;">
                <div class="therapy-header">
                    <div class="therapy-icon" style="background: <?php echo $therapy['color']; ?>;">
                        <i class="bi <?php echo $therapy['icon']; ?>"></i>
                    </div>
                    <div>
                        <div class="d-flex align-items-center">
                            <h2 class="therapy-title"><?php echo $therapy['title']; ?></h2>
                            <?php if ($recommendedTherapy == $type): ?>
                            <span class="badge bg-success ms-3">Recommended for You</span>
                            <?php endif; ?>
                        </div>
                        <p class="therapy-description"><?php echo $therapy['description']; ?></p>
                    </div>
                </div>
                
                <div class="therapy-tip">
                    <h6><i class="bi bi-lightbulb me-2"></i> Quick Tip</h6>
                    <p class="mb-0 small">
                        <?php if ($type == 'Speech'): ?>
                        Practice short sessions (5-10 minutes) daily rather than long infrequent sessions.
                        <?php elseif ($type == 'OT'): ?>
                        Incorporate therapy activities into daily routines like dressing and eating.
                        <?php elseif ($type == 'ABA'): ?>
                        Consistency is key - use the same cues and rewards each time.
                        <?php elseif ($type == 'Play'): ?>
                        Follow your child's lead and interests during play sessions.
                        <?php endif; ?>
                    </p>
                </div>
                
                <div class="mt-4">
                    <h5 class="mb-3"><i class="bi bi-collection me-2"></i> Available Resources</h5>
                    
                    <?php foreach ($therapy['resources'] as $index => $resource): 
                        $resourceId = "{$type}_{$index}";
                        $isBookmarked = in_array($resourceId, $bookmarkedIds);
                    ?>
                    <div class="resource-item">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <div class="d-flex align-items-start">
                                    <div class="me-3">
                                        <span class="resource-type-badge resource-type-<?php echo $resource['type']; ?>">
                                            <?php echo strtoupper($resource['type']); ?>
                                        </span>
                                    </div>
                                    <div>
                                        <h6 class="mb-1"><?php echo $resource['title']; ?></h6>
                                        <p class="mb-2 text-muted small"><?php echo $resource['description']; ?></p>
                                        <div class="resource-duration">
                                            <i class="bi bi-clock"></i>
                                            <?php echo $resource['duration']; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4 text-md-end">
                                <div class="d-flex justify-content-end align-items-center gap-3">
                                    <?php if ($tableCheck && $tableCheck->num_rows > 0): ?>
                                    <button class="bookmark-btn <?php echo $isBookmarked ? 'active' : ''; ?>" 
                                            data-resource-id="<?php echo $resourceId; ?>"
                                            onclick="toggleBookmark(this)">
                                        <i class="bi <?php echo $isBookmarked ? 'bi-bookmark-fill' : 'bi-bookmark'; ?>"></i>
                                    </button>
                                    <?php endif; ?>
                                    <a href="<?php echo $resource['url']; ?>" class="btn btn-sm btn-primary">
                                        <i class="bi bi-download me-1"></i> Access
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Additional Actions -->
                <div class="d-flex justify-content-between align-items-center mt-4 pt-3 border-top">
                    <div>
                        <small class="text-muted">
                            <i class="bi bi-info-circle me-1"></i>
                            <?php echo count($therapy['resources']); ?> resources available
                        </small>
                    </div>
                    <div>
                        <a href="find-therapist.php?specialty=<?php echo urlencode($type); ?>" 
                           class="btn btn-outline-primary btn-sm me-2">
                            <i class="bi bi-person-badge me-1"></i> Find <?php echo $type; ?> Therapists
                        </a>
                        <a href="book_appointment.php?therapy=<?php echo urlencode($type); ?>" 
                           class="btn btn-primary btn-sm">
                            <i class="bi bi-calendar-plus me-1"></i> Book Consultation
                        </a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            
        <?php else: ?>
            <!-- Empty State -->
            <div class="empty-state">
                <div class="empty-state-icon">
                    <i class="bi bi-folder-x"></i>
                </div>
                <h3 class="mb-3">No Resources Found</h3>
                <p class="text-muted mb-4">
                    <?php if ($therapyType): ?>
                        No resources available for "<?php echo htmlspecialchars($therapyType); ?>" therapy.
                    <?php else: ?>
                        No therapy resources available at the moment.
                    <?php endif; ?>
                </p>
                <a href="therapy-resources.php" class="btn btn-primary">
                    <i class="bi bi-arrow-left me-2"></i> Back to All Resources
                </a>
            </div>
        <?php endif; ?>
    </div>
    
    <?php include 'includes/footer.php'; ?>
    
    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Toggle bookmark
        function toggleBookmark(button) {
            const resourceId = button.getAttribute('data-resource-id');
            const isCurrentlyBookmarked = button.classList.contains('active');
            
            // Toggle UI
            button.classList.toggle('active');
            const icon = button.querySelector('i');
            icon.classList.toggle('bi-bookmark');
            icon.classList.toggle('bi-bookmark-fill');
            
            // Send AJAX request
            $.ajax({
                url: 'toggle-bookmark.php',
                method: 'POST',
                data: {
                    resource_id: resourceId,
                    action: isCurrentlyBookmarked ? 'remove' : 'add'
                },
                success: function(response) {
                    console.log('Bookmark updated');
                },
                error: function() {
                    // Revert UI on error
                    button.classList.toggle('active');
                    icon.classList.toggle('bi-bookmark');
                    icon.classList.toggle('bi-bookmark-fill');
                    alert('Failed to update bookmark. Please try again.');
                }
            });
        }
        
        // Animate cards on scroll
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }
            });
        }, { threshold: 0.1 });
        
        // Observe all therapy cards
        document.querySelectorAll('.therapy-card').forEach(card => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(20px)';
            card.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
            observer.observe(card);
        });
        
        // Filter resources by type
        function filterResources(type) {
            if (type === 'all') {
                window.location.href = 'therapy-resources.php';
            } else {
                window.location.href = 'therapy-resources.php?therapy=' + encodeURIComponent(type);
            }
        }
        
        // Initialize tooltips
        document.addEventListener('DOMContentLoaded', function() {
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        });
    </script>
</body>
</html>