<?php
//TODO: I started changing to inline variable names in strings rather than "example ".$variable." string" - need to finish
date_default_timezone_set("America/New_York");

require_once('workflows.php');
$w = new Workflows();

$icon = "icon.png";

$hour = date("H");
if($hour < 3){ // if it's before 3 a.m. we're probably asking for yesterday's game, not the game coming up
	$day = date("d",strtotime('-1 days'));
	$month = date("m",strtotime('-1 days'));
	$year = date("Y",strtotime('-1 days'));
}else{
	$day = date("d");
	$month = date("m");
	$year = date("Y");
}

$team = "NYY";

$url = "http://gd2.mlb.com/components/game/mlb/year_$year/month_$month/day_".sprintf('%02d',$day)."/master_scoreboard.json?now=".date("dmyhms");

$data = $w->request($url);
$data = json_decode($data);

$data = $data->data->games;

$awayTeams = array_column($data->game, 'away_name_abbrev');
$homeTeams = array_column($data->game, 'home_name_abbrev');

$homeaway = '';
$n = -1;
if(in_array($team,$awayTeams)){
	$homeaway = 'away';
	$n = array_keys($awayTeams, $team);
}elseif(in_array($team,$homeTeams)){
	$homeaway = 'home';
	$n = array_keys($homeTeams, $team);
}

$tot_n = count($n);

if($homeaway == "" || $tot_n < 0){
	$w->result(0, 'na', "No $team game found for today", "", $icon, "no");
}else{
	$d = 0;
	for($gn = 0; $gn < $tot_n; $gn++){
		$game = $data->game[$n[$gn]];
		if(strcasecmp($game->status->status, "In Progress") == 0){
			//echo $game->away_team_name.":". $game->linescore->r->away." | ".$game->home_team_name.": ".$game->linescore->r->home;
			$info = $game->away_team_name." ". $game->linescore->r->away." - ".$game->linescore->r->home." ".$game->home_team_name;
			$info .= " (".$game->status->inning_state." of the ".$game->status->inning;
			switch($game->status->inning){
				case 1:
				$info .= "st";
				break;
				case 2:
				$info .= "nd";
				break;
				case 3:
				$info .= "rd";
				break;
				default:
				$info .= "th";
			}
			$info .= ")";
			$w->result($d++, "na", $info, "Pitcher: ".$game->pitcher->last." (".$game->pitcher->era.") | Batter: ".$game->batter->last. " (".$game->batter->avg.")", $icon, "no");
			// linescore (r, h & e - both teams)
			$w->result($d++, "na", "R: ".$game->linescore->r->home." - ".$game->linescore->r->away." | H: ".$game->linescore->h->home." - ".$game->linescore->h->away." | E:".$game->linescore->e->home." - ".$game->linescore->e->away, "$game->home_team_name | ".$game->away_team_name, $icon, "no");
			// b, s, o
			if(strcasecmp($game->status->inning_state, "top") == 0){
				$inning_info = "$game->home_team_name fielding (".$game->pitcher->last." pitching)";
			}elseif(strcasecmp($game->status->inning_state, "bottom") == 0){
				$inning_info = "$game->home_team_name batting (".$game->batter->last." at bat)";
			}else{
				$inning_info = $game->status->inning_state." of inning";
			}
			$w->result($d++, "na", "Balls: ".$game->status->b." | Strikes: ".$game->status->s." | Outs: ".$game->status->o, $inning_info, $icon, "no");
			// runners_on_base
			$bases = array("-","-","-");
			$base_icon = "000";
			$b = 0;
			if(property_exists($game->runners_on_base,'runner_on_1b')){
				$bases[0] = "1st: ".$game->runners_on_base->runner_on_1b->last;
				$base_icon[0] = "1";
				$b++;
			}
			if(property_exists($game->runners_on_base,'runner_on_2b')){
				$bases[1] = "2nd: ".$game->runners_on_base->runner_on_2b->last;
				$base_icon[1] = "1";
				$b++;
			}
			if(property_exists($game->runners_on_base,'runner_on_3b')){
				$bases[2] = "3rd: ".$game->runners_on_base->runner_on_3b->last;
				$base_icon[2] = "1";
				$b++;
			}
			$base_sub = $b." runner";
			if($base_sub != 1) $base_sub .= "s";
			$base_sub .= " on base";
			if($base_sub != 1) $base_sub .= "s";
			$w->result($d++, "na", implode($bases," | "), $base_sub, $base_icon.".png", "no");
			// away_win, away_loss, home_win, home_loss
			$w->result($d++, "na", "$game->home_team_name: ".$game->home_win."-".$game->home_loss, $game->away_team_name.": ".$game->away_win."-".$game->away_loss, $icon, "no");
			$w->result($d++, "na", "TV: ".$game->broadcast->$homeaway->tv, "Radio: ".$game->broadcast->$homeaway->radio, $icon, "no");
		}elseif(strcasecmp($game->status->status, "Preview") == 0 || strcasecmp($game->status->status, "Pre-Game") == 0){
			if($homeaway == "home"){
				$gametime = $game->home_time;
				$ampm = $game->home_ampm;
			}else{
				$gametime = $game->away_time;
				$ampm = $game->away_ampm;
			}
			$w->result($d++, "na", $game->away_name_abbrev." @ ".$game->home_name_abbrev." at ".$gametime." ".$ampm." (ET)", $game->venue, $icon, "no");
			$w->result($d++, "na", "$game->home_team_name: ".$game->home_win."-".$game->home_loss, $game->away_team_name.": ".$game->away_win."-".$game->away_loss, $icon, "no");
			$w->result($d++, "na", "TV: ".$game->broadcast->$homeaway->tv, "Radio: ".$game->broadcast->$homeaway->radio, $icon, "no");
		}elseif(strcasecmp($game->status->status, "Final") == 0 || strcasecmp($game->status->status, "Game Over") == 0){
			$w->result($d++, "na", "Final: $game->home_team_name ".$game->linescore->r->home." - ".$game->linescore->r->away." ".$game->away_team_name, "R: ".$game->linescore->r->home." - ".$game->linescore->r->away." | H: ".$game->linescore->h->home." - ".$game->linescore->h->away." | E: ".$game->linescore->e->home." - ".$game->linescore->e->away."", $icon, "no");
		}elseif(strcasecmp($game->status->status, "Warmup") == 0){
			$w->result($d++, "na", "Warmup", "Gettin' ready...", $icon, "no");
		}else{
			$w->result($d++, "na", "Unknown game state", "Can't find details for game status: ".$game->status->status, $icon, "no");
		}
	}
}

echo $w->toxml();
?>
