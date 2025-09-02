# Sports Teams API

A simple **RESTful API** built with **PHP and MySQL** to manage sports teams and their players.  
It supports viewing teams, listing players, and performing CRUD operations on players.

## Features
- View all teams with their average player age.
- View all players in a team or a specific player.
- Add, update, or delete players in a team.
- JSON responses for easy integration.

## Tech Stack
- **Backend:** PHP (PDO)
- **Database:** MySQL
- **Format:** JSON (REST API)

## Endpoints
| Method | Endpoint | Description |
|--------|-----------|-------------|
| GET | `/teams` | Get all teams |
| GET | `/teams/{teamId}/players` | List all players in a team |
| GET | `/teams/{teamId}/players/{playerId}` | Get a specific player |
| POST | `/teams/{teamId}/players` | Add a new player |
| PATCH | `/teams/{teamId}/players/{playerId}` | Update player details |
| DELETE | `/teams/{teamId}/players/{playerId}` | Delete a player |

## Setup
1. Clone the repo.
2. Create a MySQL database and update `config.php` with your credentials.
3. Start a PHP server:
   ```bash
   php -S localhost:8000
