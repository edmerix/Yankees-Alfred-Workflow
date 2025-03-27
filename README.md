# Yankees Alfred Workflow

Workflow for Alfred that uses the MLB API to check info about a current Yankees game (i.e. quickly check the score, who's on base, who's pitching etc.)

_(For a team-agnostic version of this check my overall [MLB version](https://github.com/edmerix/MLB-Alfred-Workflow)) <sub><sup>But why...?</sup></sub>_

Requires PHP installed, by default via homebrew and in `/opt/homebrew/bin/php.` This can be changed in the script filter after installation.

Shows runners on base at a glance using icons in the Alfred response, see [screenshots](#screenshots) below.

If a game has already finish today it'll show the final result, including R|H|E, if a game is scheduled for today it'll give upcoming info.

## Screenshots

### Example response during a game
![Screenshot of workflow during game](screenshots/active_game.png?raw=true "A screenshot of workflow during game")

### Example response after a game finishes
![Screenshot of workflow after a game](screenshots/final.png?raw=true "A screenshot of workflow after a game")

### Example response with a game later this day
![Screenshot of workflow before a game](screenshots/upcoming.png?raw=true "A screenshot of workflow before a game")

## Misc

It also shows who's pitching (_+stats_), who's batting (_+stats_), current balls/strikes, if anyone's (and who's) on base, current win-lose record for both teams, and where the game is being broadcast on TV and radio.

Uses [David Ferguson](http://dferg.us)'s [Workflow PHP class](https://github.com/jdfwarrior/Workflows).
