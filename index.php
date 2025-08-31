<?php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PATCH, DELETE");
header("Access-Control-Allow-Headers: Content-Type");

require_once 'config.php';

// Database connection
try {
    $db = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => "Database connection failed"]);
    exit;
}

// Get request method
$request_method = $_SERVER['REQUEST_METHOD'];

// Parse request
if (isset($_GET['resource'])) {
    // Handle direct index.php?resource=teams calls
    $endpoint_parts = explode('/', $_GET['resource']);
} else {
    // Handle clean URL calls
    $request_uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $base_path = '/~hslcabal/v1/';
    $endpoint = str_replace($base_path, '', $request_uri);
    $endpoint_parts = array_filter(explode('/', $endpoint));
}

// Handle requests
switch ($endpoint_parts[0]) {
    case 'teams':
        handleTeamRequests($db, $request_method, $endpoint_parts);
        break;
    default:
        http_response_code(404);
        echo json_encode(["error" => "Endpoint not found"]);
        break;
}

// Team/Player request handler
function handleTeamRequests($db, $method, $parts) {
    switch (true) {
        // GET /teams
        case ($method == 'GET' && count($parts) == 1):
            getTeams($db);
            break;
            
        // Block all non-GET team operations
        case ($method != 'GET' && count($parts) == 1):
            http_response_code(405);
            echo json_encode(["error" => "Team modifications are not supported"]);
            break;
            
        // GET /teams/{id}/players
        case ($method == 'GET' && count($parts) == 3 && $parts[2] == 'players'):
            getTeamPlayers($db, $parts[1]);
            break;
            
        // GET /teams/{id}/players/{playerId}
        case ($method == 'GET' && count($parts) == 4 && $parts[2] == 'players'):
            getPlayer($db, $parts[1], $parts[3]);
            break;
            
        // POST /teams/{id}/players
        case ($method == 'POST' && count($parts) == 3 && $parts[2] == 'players'):
            addPlayer($db, $parts[1]);
            break;
            
        // PATCH /teams/{id}/players/{playerId}
        case ($method == 'PATCH' && count($parts) == 4 && $parts[2] == 'players'):
            updatePlayer($db, $parts[1], $parts[3]);
            break;
            
        // DELETE /teams/{id}/players/{playerId}
        case ($method == 'DELETE' && count($parts) == 4 && $parts[2] == 'players'):
            deletePlayer($db, $parts[1], $parts[3]);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(["error" => "Method not allowed"]);
            break;
    }
}

// --- Helper Functions ---

// Get all teams
function getTeams($db) {
    try {
        $stmt = $db->query("SELECT team_id, name, sport FROM teams ORDER BY name");
        $teams = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($teams as &$team) {
            // Calculate average age using two different methods for verification
            $stmt = $db->prepare("SELECT 
                AVG(TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE())) as avg_age,
                AVG(DATEDIFF(CURDATE(), date_of_birth)/365.25) as alt_avg_age,
                COUNT(player_id) as player_count
                FROM players WHERE team_id = ?");
            $stmt->execute([$team['team_id']]);
            $age_data = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Verify calculation methods produce similar results (within 0.1 years)
            if ($age_data['player_count'] > 0) {
                $difference = abs($age_data['avg_age'] - $age_data['alt_avg_age']);
                if ($difference > 0.1) {
                    error_log("Age calculation discrepancy for team {$team['team_id']}: " . 
                             "TIMESTAMPDIFF={$age_data['avg_age']} vs " .
                             "DATEDIFF={$age_data['alt_avg_age']}");
                }
            }
            
            $team['average_age'] = round($age_data['avg_age'], 1);
            $team['players_path'] = "teams/{$team['team_id']}/players";
        }
        
        http_response_code(200);
        echo json_encode($teams);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["error" => "Database error"]);
    }
}

// Get all players in a team
function getTeamPlayers($db, $team_id) {
    try {
        // Check if team exists
        $stmt = $db->prepare("SELECT team_id FROM teams WHERE team_id = ?");
        $stmt->execute([$team_id]);
        if ($stmt->rowCount() == 0) {
            http_response_code(404);
            echo json_encode(["error" => "Team not found"]);
            return;
        }
        
        $stmt = $db->prepare("SELECT player_id, surname, given_names, nationality, date_of_birth FROM players WHERE team_id = ?");
        $stmt->execute([$team_id]);
        $players = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        http_response_code(200);
        echo json_encode($players);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["error" => "Database error"]);
    }
}

// Get a specific player
function getPlayer($db, $team_id, $player_id) {
    try {
        // Check if team exists
        $stmt = $db->prepare("SELECT team_id FROM teams WHERE team_id = ?");
        $stmt->execute([$team_id]);
        if ($stmt->rowCount() == 0) {
            http_response_code(404);
            echo json_encode(["error" => "Team not found"]);
            return;
        }
        
        $stmt = $db->prepare("SELECT player_id, surname, given_names, nationality, date_of_birth FROM players WHERE team_id = ? AND player_id = ?");
        $stmt->execute([$team_id, $player_id]);
        
        if ($stmt->rowCount() == 0) {
            http_response_code(404);
            echo json_encode(["error" => "Player not found"]);
            return;
        }
        
        $player = $stmt->fetch(PDO::FETCH_ASSOC);
        http_response_code(200);
        echo json_encode($player);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["error" => "Database error"]);
    }
}

// Add a player to a team
function addPlayer($db, $team_id) {
    try {
        // Check if team exists
        $stmt = $db->prepare("SELECT team_id FROM teams WHERE team_id = ?");
        $stmt->execute([$team_id]);
        if ($stmt->rowCount() == 0) {
            http_response_code(404);
            echo json_encode(["error" => "Team not found"]);
            return;
        }
        
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);
        
        if (json_last_error() !== JSON_ERROR_NONE || 
            !isset($data['surname']) || 
            !isset($data['given_names']) || 
            !isset($data['nationality']) || 
            !isset($data['date_of_birth'])) {
            http_response_code(400);
            echo json_encode(["error" => "Invalid player data"]);
            return;
        }
        
        $stmt = $db->prepare("INSERT INTO players (team_id, surname, given_names, nationality, date_of_birth) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([
            $team_id,
            $data['surname'],
            $data['given_names'],
            $data['nationality'],
            $data['date_of_birth']
        ]);
        
        $player_id = $db->lastInsertId();
        http_response_code(201);
        echo json_encode([
            "message" => "Player added successfully",
            "player_id" => $player_id,
            "player_path" => "teams/$team_id/players/$player_id"
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["error" => "Database error"]);
    }
}

// Update a player
function updatePlayer($db, $team_id, $player_id) {
    try {
        // Check if team exists
        $stmt = $db->prepare("SELECT team_id FROM teams WHERE team_id = ?");
        $stmt->execute([$team_id]);
        if ($stmt->rowCount() == 0) {
            http_response_code(404);
            echo json_encode(["error" => "Team not found"]);
            return;
        }
        
        // Check if player exists
        $stmt = $db->prepare("SELECT player_id FROM players WHERE team_id = ? AND player_id = ?");
        $stmt->execute([$team_id, $player_id]);
        if ($stmt->rowCount() == 0) {
            http_response_code(404);
            echo json_encode(["error" => "Player not found"]);
            return;
        }
        
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            http_response_code(400);
            echo json_encode(["error" => "Invalid JSON data"]);
            return;
        }
        
        // Build dynamic update query
        $fields = [];
        $params = [];
        
        if (isset($data['surname'])) {
            $fields[] = "surname = ?";
            $params[] = $data['surname'];
        }
        if (isset($data['given_names'])) {
            $fields[] = "given_names = ?";
            $params[] = $data['given_names'];
        }
        if (isset($data['nationality'])) {
            $fields[] = "nationality = ?";
            $params[] = $data['nationality'];
        }
        if (isset($data['date_of_birth'])) {
            $fields[] = "date_of_birth = ?";
            $params[] = $data['date_of_birth'];
        }
        
        if (empty($fields)) {
            http_response_code(400);
            echo json_encode(["error" => "No fields to update"]);
            return;
        }
        
        $params[] = $team_id;
        $params[] = $player_id;
        
        $query = "UPDATE players SET " . implode(', ', $fields) . " WHERE team_id = ? AND player_id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        
        http_response_code(200);
        echo json_encode(["message" => "Player updated successfully"]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["error" => "Database error"]);
    }
}

// Delete a player
function deletePlayer($db, $team_id, $player_id) {
    try {
        // Check if team exists
        $stmt = $db->prepare("SELECT team_id FROM teams WHERE team_id = ?");
        $stmt->execute([$team_id]);
        if ($stmt->rowCount() == 0) {
            http_response_code(404);
            echo json_encode(["error" => "Team not found"]);
            return;
        }
        
        // Check if player exists
        $stmt = $db->prepare("SELECT player_id FROM players WHERE team_id = ? AND player_id = ?");
        $stmt->execute([$team_id, $player_id]);
        if ($stmt->rowCount() == 0) {
            http_response_code(404);
            echo json_encode(["error" => "Player not found"]);
            return;
        }
        
        $stmt = $db->prepare("DELETE FROM players WHERE team_id = ? AND player_id = ?");
        $stmt->execute([$team_id, $player_id]);
        
        http_response_code(200);
        echo json_encode(["message" => "Player deleted successfully"]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["error" => "Database error"]);
    }
}
?>
