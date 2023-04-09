<?php
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

$url = "http://statsapi.mlb.com/api/v1/schedule/games/?sportId=1&date=$year-$month-".sprintf('%02d',$day)."&".time();

$data = $w->request($url);
$data = json_decode($data);

$games = $data->dates[0]->games;

$awayGames = [];
$homeGames = [];

for($g = 0; $g < count($games); $g++){
	if(strcasecmp($games[$g]->teams->away->team->name,"New York Yankees") == 0){
		$awayGames[] = $g;
	}elseif(strcasecmp($games[$g]->teams->home->team->name,"New York Yankees") == 0){
		$homeGames[] = $g;
	}
}

$allGames = $awayGames + $homeGames;
if(count($allGames) < 0){
	$w->result(0, 'na', "No Yankees game found for today", "", $icon, "no");
	return;
}

$d = 0;
for($n = 0; $n < count($allGames); $n++){
	$status = $games[$allGames[$n]]->status->statusCode;
	if(in_array($allGames[$n],$homeGames)){
		$homeaway = 'home';
	}elseif(in_array($allGames[$n],$awayGames)){
		$homeaway = 'away';
	}else{
		$homeaway = 'unknown';
	}
	$homeawayInv = array('home' => 'away', 'away' => 'home'); // easy way to search the opposite

	// Information about teams (e.g. team name rather than full name)
	$homeTeamLink = $games[$allGames[$n]]->teams->home->team->link;
	$awayTeamLink = $games[$allGames[$n]]->teams->away->team->link;
	$homeTeamURL = "http://statsapi.mlb.com/$homeTeamLink";
	$homeTeamData = $w->request($homeTeamURL);
	$homeTeamData = json_decode($homeTeamData);
	$awayTeamURL = "http://statsapi.mlb.com/$awayTeamLink";
	$awayTeamData = $w->request($awayTeamURL);
	$awayTeamData = json_decode($awayTeamData);
	
	$homeAbbr = $homeTeamData->teams[0]->teamName;
	$awayAbbr = $awayTeamData->teams[0]->teamName;
	
	
	$winPct = $games[$allGames[$n]]->teams->$homeaway->leagueRecord->pct;
	$winRecord = $games[$allGames[$n]]->teams->$homeaway->leagueRecord->wins;
	$lossRecord = $games[$allGames[$n]]->teams->$homeaway->leagueRecord->losses;

	$swap = $homeawayInv[$homeaway];
	$opponentName = $games[$allGames[$n]]->teams->$swap->team->name;
	$opponentWinPct = $games[$allGames[$n]]->teams->$swap->leagueRecord->pct;
	$opponentWinRecord = $games[$allGames[$n]]->teams->$swap->leagueRecord->wins;
	$opponentLossRecord = $games[$allGames[$n]]->teams->$swap->leagueRecord->losses;
	
	$location = $games[$allGames[$n]]->venue->name;
	$gameInSeries = $games[$allGames[$n]]->seriesGameNumber;
	$totalInSeries = $games[$allGames[$n]]->gamesInSeries;
	
	$contentLink = $games[$allGames[$n]]->content->link;
	$contentURL = "http://statsapi.mlb.com/$contentLink";
	$contentData = $w->request($contentURL);
	$contentData = json_decode($contentData);

	$broadcasters = array_column($contentData->media->epg[0]->items, 'callLetters');
	$broadcastList = implode("; ", $broadcasters);
	
	$gameLink = $games[$allGames[$n]]->link;
	$gameURL = "http://statsapi.mlb.com/$gameLink";
	$gameData = $w->request($gameURL);
	$gameData = json_decode($gameData);
	
	switch($status){
		case "F":  //Final
			if($games[$allGames[$n]]->teams->$homeaway->isWinner){
				$winlose = ": win!";
			}else{
				$winlose = " :( ";
			}
			$yankeesScore = $games[$allGames[$n]]->teams->$homeaway->score;
			$opponentScore = $games[$allGames[$n]]->teams->$swap->score;

			$w->result($d++, "na", "Final$winlose Yankees $yankeesScore – $opponentScore $opponentName", "Yankees: $winPct ($winRecord - $lossRecord) | $opponentName: $opponentWinPct ($opponentWinRecord - $opponentLossRecord)", $icon, "no");
			break;
		case "S":  //Scheduled
		case "P":  //Pre-game
		case "PW": //Pre-game, warmup
			$scheduledTime = strtotime($games[$allGames[$n]]->gameDate);
			$startTime = date('H:i', $scheduledTime);
			
			$ourPitcher = $gameData->gameData->probablePitchers->$homeaway->fullName;
			$theirPitcher = $gameData->gameData->probablePitchers->$swap->fullName;
			
			$w->result($d++, "na", "Against $opponentName ($homeaway game) @ $startTime", "At $location", $icon, "no");
			$w->result($d++, "na", "Game $gameInSeries in $totalInSeries game series", "Yankees: $winPct ($winRecord - $lossRecord) | $opponentName: $opponentWinPct ($opponentWinRecord - $opponentLossRecord)", $icon, "no");
			$w->result($d++, "na", "Likely starting pitcher: $ourPitcher", "$opponentName likely starting pitcher: $theirPitcher", $icon, "no");
			$w->result($d++, "na", "Broadcast on: $broadcastList", "", $icon, "no");
			break;
		case "I":  //In-game
			$game = $games[$allGames[$n]]; // necessary?
			$gameID = $game->gamePk; // necessary?
			
			$linescore = $gameData->liveData->linescore;
			$inning = $linescore->currentInningOrdinal;
			$inningState = $linescore->inningState;
			$balls = $linescore->balls;
			$strikes = $linescore->strikes;
			$outs = $linescore->outs;
			
			$runners = $gameData->liveData->plays->currentPlay->runners;
			
			$pitcher = $linescore->defense->pitcher->fullName;
			$atBat = $linescore->offense->batter->fullName;
			$battingOrder = $linescore->offense->battingOrder;
			$inHole = $linescore->offense->inHole->fullName;
			$onDeck = $linescore->offense->onDeck->fullName;
			
			$homeNoHitter = $gameData->gameData->flags->homeTeamNoHitter;
			$homePerfectGame = $gameData->gameData->flags->homeTeamPerfectGame;
			$awayNoHitter = $gameData->gameData->flags->awayTeamNoHitter;
			$awayPerfectGame = $gameData->gameData->flags->awayTeamPerfectGame;
			
			$runs = array('home' => $linescore->teams->home->runs, 'away' => $linescore->teams->away->runs);
			$hits = array('home' => $linescore->teams->home->hits, 'away' => $linescore->teams->away->hits);
			$errors = array('home' => $linescore->teams->home->errors, 'away' => $linescore->teams->away->errors);
			$leftOnBase = array('home' => $linescore->teams->home->leftOnBase, 'away' => $linescore->teams->away->leftOnBase);
			
			$awayName = $games[$allGames[$n]]->teams->away->team->name;
			$homeName = $games[$allGames[$n]]->teams->home->team->name;
			
			$topLine = "$awayAbbr {$runs['away']} – {$runs['home']} $homeAbbr ($inningState of the $inning)";
			$subLine = "At bat: $battingOrder. $atBat ($inHole in the hole; $onDeck on deck)";
			$w->result($d++, "na", $topLine, $subLine, $icon, "no");
			
			$topLine = "R: {$runs['away']} - {$runs['home']} | H: {$hits['away']} - {$hits['home']} | E: {$errors['away']} - {$errors['home']}";
			$subLine = "LOB: {$leftOnBase['away']} | {$leftOnBase['home']} ($awayAbbr | $homeAbbr)";
			$w->result($d++, "na", $topLine, $subLine, $icon, "no");
			
			$topLine = "Balls: $balls | Strikes: $strikes | Outs: $outs";
			$subLine = "Pitcher: $pitcher";
			$w->result($d++, "na", $topLine, $subLine, $icon, "no");
			
			// runners_on_base
			$bases = array("-","-","-");
			$base_icon = "000";
			for($r = 0; $r < count($runners); $r++){
				if(!$runners[$r]->movement->isOut){
					$where = $runners[$r]->movement->end;
					switch($where){
						case "1B":
						$bases[0] = "1st: {$runners[$r]->details->runner->fullName}";
						$base_icon[0] = "1";
						break;
						case "2B":
						$bases[1] = "2nd: {$runners[$r]->details->runner->fullName}";
						$base_icon[1] = "1";
						break;
						case "3B":
						$bases[2] = "3rd: {$runners[$r]->details->runner->fullName}";
						$base_icon[2] = "1";
						break;
					}
				}				
			}
			
			$b = count($runners);
			$base_sub = $b." runner";
			if($b != 1) $base_sub .= "s";
			$base_sub .= " on base";
			if($base_sub != 1) $base_sub .= "s";
			$w->result($d++, "na", implode(" | ",$bases), $base_sub, $base_icon.".png", "no");
			
			switch($homeaway){
				case "away":
					$opponentAbbr = $homeAbbr;
					break;
				case "home":
					$opponentAbbr = $awayAbbr;
					break;
				default:
					$opponentAbbr = $opponentName;
			}
			$topLine = "Yankees: $winPct ($winRecord - $lossRecord) | $opponentAbbr: $opponentWinPct ($opponentWinRecord - $opponentLossRecord)";
			$subLine = "Game $gameInSeries in $totalInSeries game series";
			$w->result($d++, "na", $topLine, $subLine, $icon, "no");
			
			$w->result($d++, "na", "Broadcast on: $broadcastList", "", $icon, "no");
			
			break;
		default:
			$w->result($d++, "na", "Unknown game state", "Can't find details for game status: $status (\"".$games[$allGames[$n]]->status->detailedState."\")", $icon, "no");
	}
	
}

echo $w->toxml();
?>
