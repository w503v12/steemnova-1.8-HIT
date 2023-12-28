<?php

/**
 *  2Moons
 *   by Jan-Otto Kröpke 2009-2016
 *
 * For the full copyright and license information, please view the LICENSE
 *
 * @package 2Moons
 * @author Jan-Otto Kröpke <slaver7@gmail.com>
 * @copyright 2009 Lucky
 * @copyright 2016 Jan-Otto Kröpke <slaver7@gmail.com>
 * @licence MIT
 * @version 1.8.x Koray Karakuş <koraykarakus@yahoo.com>
 * @link https://github.com/jkroepke/2Moons
 */

class MissionCaseExpedition extends MissionFunctions implements Mission
{
	function __construct($fleet)
	{
		$this->_fleet	= $fleet;
	}

	function TargetEvent()
	{
		$this->setState(FLEET_HOLD);
		$this->SaveFleet();
	}

	function EndStayEvent()
	{
		global $pricelist, $reslist, $resource;
		$LNG	= $this->getLanguage(NULL, $this->_fleet['fleet_owner']);

		$config	= Config::get($this->_fleet['fleet_universe']);

		$expeditionPoints       = array();

		foreach($reslist['fleet'] as $shipId)
		{
			$expeditionPoints[$shipId]	= ($pricelist[$shipId]['cost'][901] + $pricelist[$shipId]['cost'][902]) * 5 / 1000;
		}

		$fleetArray		= FleetFunctions::unserialize($this->_fleet['fleet_array']);
		$fleetPoints 	= 0;
		$fleetCapacity	= 0;

		foreach ($fleetArray as $shipId => $shipAmount)
		{
			$fleetCapacity 			   += $shipAmount * $pricelist[$shipId]['capacity'];
			$fleetPoints   			   += $shipAmount * $expeditionPoints[$shipId];
		}

		$fleetCapacity  -= $this->_fleet['fleet_resource_metal'] + $this->_fleet['fleet_resource_crystal']
			+ $this->_fleet['fleet_resource_deuterium'] + $this->_fleet['fleet_resource_darkmatter'];

		// Get Expeditions count in this system
		$sql = "SELECT COUNT(*) AS total FROM %%LOG_FLEETS%% where `fleet_end_galaxy` = :fleet_end_galaxy and `fleet_end_system` = :fleet_end_system and `fleet_end_planet` = :fleet_end_planet and `fleet_end_stay` > UNIX_TIMESTAMP(NOW() - INTERVAL 1 DAY)";

		$expeditionsCount = Database::get()->selectSingle($sql, array(
			'fleet_end_galaxy' => $this->_fleet['fleet_end_galaxy'],
			'fleet_end_system' => $this->_fleet['fleet_end_system'],
			'fleet_end_planet' => $this->_fleet['fleet_end_planet']
		), 'total');


		// Get a seed into the number generator (to make the results unpredictable).
		mt_srand((int) (microtime(TRUE) * 10000));
		usleep(50);
		$GetEvent = mt_rand(0, 1000);

		// Hold time bonus
		if ($config->expedition_consider_holdtime) {
			$holdTime = ($this->_fleet['fleet_end_stay'] - $this->_fleet['fleet_start_time']) / 3600;
			$GetEvent -= $holdTime * 10;
		}



		$Message = $LNG['sys_expe_nothing_'.mt_rand(1,8)];
		$logbook = "";
		// Depletion check

		if ($config->expedition_consider_same_coordinate) {
			if ($expeditionsCount <= 10) {
				$chanceDepleted = 0;
				$logbook = $LNG['sys_expe_depleted_not_'.mt_rand(1,2)];
			}
			else if ($expeditionsCount <= 25) {
				$chanceDepleted = 25;
				$logbook = $LNG['sys_expe_depleted_min_'.mt_rand(1,3)];
			}
			else if ($expeditionsCount <= 50) {
				$chanceDepleted = 50;
				$logbook = $LNG['sys_expe_depleted_med_'.mt_rand(1,3)];
			}
			else {
				$chanceDepleted = 75;
				$logbook = $LNG['sys_expe_depleted_max_'.mt_rand(1,3)];
			}

			$depleted = mt_rand(0, 100);
			if ($depleted < $chanceDepleted)
				$GetEvent = 1000; // nothing happens

		}




		do {

			// Find resources: 32,5%. Values from http://owiki.de/Expedition
			if ($GetEvent < 325)
			{

				if (!$config->expedition_allow_resources_find) { break; }

				$eventSize		= mt_rand(0, 100);
				$factor			= 0;

				// normal (89%)
				if(10 < $eventSize)
				{
					$Message	= $LNG['sys_expe_found_ress_1_'.mt_rand(1,4)];
					$factor		= mt_rand(10, 50);
				}
				// large (10%)
				elseif(0 < $eventSize && 10 >= $eventSize)
				{
					$Message	= $LNG['sys_expe_found_ress_2_'.mt_rand(1,3)];
					$factor		= mt_rand(50, 100);
				}
				// very large (1%)
				elseif(0 == $eventSize)
				{
					$Message	= $LNG['sys_expe_found_ress_3_'.mt_rand(1,2)];
					$factor		= mt_rand(100, 200);
				}

				$chanceToFound	= mt_rand(1, 6);
				if($chanceToFound > 3)
				{
					$resourceId	= 901;
				}
				elseif($chanceToFound > 1)
				{
					$resourceId	= 902;
					$factor		= $factor / 2;
				}
				else
				{
					$resourceId	= 903;
					$factor		= $factor / 3;
				}

				$sql		= "SELECT MAX(total_points) as total FROM %%USER_POINTS%%
				WHERE `universe` = :universe;";

				$topPoints	= Database::get()->selectSingle($sql, array(
					':universe'	=> $this->_fleet['fleet_universe']
				), 'total');

				if($topPoints > 100000000)
				{
					$maxFactor		= 25000;
				}
				elseif($topPoints > 75000000)
				{
					$maxFactor		= 21000;
				}
				elseif($topPoints > 50000000)
				{
					$maxFactor		= 18000;
				}
				elseif($topPoints > 25000000)
				{
					$maxFactor		= 15000;
				}
				elseif($topPoints > 5000000)
				{
					$maxFactor		= 12000;
				}
				elseif($topPoints > 1000000)
				{
					$maxFactor		= 9000;
				}
				elseif($topPoints > 100000)
				{
					$maxFactor		= 6000;
				}
				else
				{
					$maxFactor		= 2400;
				}

				$founded		= min($factor * max(min($fleetPoints, $maxFactor), 200), $fleetCapacity);

				if ($fleetPoints < $maxFactor)
					$logbook = $LNG['sys_expe_found_ress_logbook_'.mt_rand(1,4)].'<br>'.$logbook;

				$fleetColName	= 'fleet_resource_'.$resource[$resourceId];
				$this->UpdateFleet($fleetColName, $this->_fleet[$fleetColName] + $founded);
			}

			// Find Dark Matter: 9%. Values from 2Moons
			elseif ($GetEvent < 415)
			{

				if (!$config->expedition_allow_darkmatter_find) { break; }

				$eventSize   = mt_rand(0, 100);
				$Size       = 0;

				// normal (89%)
				if(10 < $eventSize) {
					$Size		= mt_rand($config->expedition_min_darkmatter_small_min, $config->expedition_min_darkmatter_small_max);
					$Message	= $LNG['sys_expe_found_dm_1_'.mt_rand(1,5)];
				}
				// large (10%)
				elseif(0 < $eventSize && 10 >= $eventSize) {
					$Size		= mt_rand($config->expedition_min_darkmatter_large_min, $config->expedition_min_darkmatter_large_max);
					$Message	= $LNG['sys_expe_found_dm_2_'.mt_rand(1,3)];
				}
				// very large (1%)
				elseif(0 == $eventSize) {
					$Size	 	= mt_rand($config->expedition_min_darkmatter_vlarge_min, $config->expedition_min_darkmatter_vlarge_max);
					$Message	= $LNG['sys_expe_found_dm_3_'.mt_rand(1,2)];
				}


				$this->UpdateFleet('fleet_resource_darkmatter', $this->_fleet['fleet_resource_darkmatter'] + $Size);
			}

			// Find abandoned ships: 22%. Values from http://owiki.de/Expedition
			elseif ($GetEvent < 635)
			{

				if (!$config->expedition_allow_ships_find) { break; }

				$eventSize	= mt_rand(0, 100);
				$Size       = 0;
				$Message    = "";

				// normal (89%)
				if(10 < $eventSize) {
					$Size		= mt_rand(10, 50);
					$Message	= $LNG['sys_expe_found_ships_1_'.mt_rand(1,4)];
				}
				// large (10%)
				elseif(0 < $eventSize && 10 >= $eventSize) {
					$Size		= mt_rand(52, 100);
					$Message	= $LNG['sys_expe_found_ships_2_'.mt_rand(1,2)];
				}
				// very large (1%)
				elseif(0 == $eventSize) {
					$Size	 	= mt_rand(102, 200);
					$Message	= $LNG['sys_expe_found_ships_3_'.mt_rand(1,2)];
				}

				$sql		= "SELECT MAX(total_points) as total FROM %%USER_POINTS%%
				WHERE `universe` = :universe;";

				$topPoints	= Database::get()->selectSingle($sql, array(
					':universe'	=> $this->_fleet['fleet_universe']
				), 'total');

				if($topPoints > 100000000)
				{
					$MaxPoints		= 12500;
				}
				elseif($topPoints > 75000000)
				{
					$MaxPoints		= 10500;
				}
				elseif($topPoints > 50000000)
				{
					$MaxPoints		= 9000;
				}
				elseif($topPoints > 25000000)
				{
					$MaxPoints		= 7500;
				}
				elseif($topPoints > 5000000)
				{
					$MaxPoints		= 6000;
				}
				elseif($topPoints > 1000000)
				{
					$MaxPoints		= 4500;
				}
				elseif($topPoints > 100000)
				{
					$MaxPoints		= 3000;
				}
				else
				{
					$MaxPoints		= 1250;
				}

				$FoundShips		= max(round($Size * min($fleetPoints, $MaxPoints)), 10000);

				if ($fleetPoints < $MaxPoints)
					$logbook = $LNG['sys_expe_found_ships_logbook_'.mt_rand(1,3)].'<br>'.$logbook;

				$FoundShipMess	= "";
				$NewFleetArray 	= "";

				$findableShips[210] = [210, 202]; # SS
				$findableShips[202] = [210, 202, 204]; # light cargo
				$findableShips[204] = [210, 202, 204, 203]; # light fighter
				$findableShips[203] = [210, 202, 204, 203, 205]; # heavy cargo
				$findableShips[205] = [210, 202, 204, 203, 205, 206]; # heavy fighter
				$findableShips[206] = [210, 202, 204, 203, 205, 206, 207]; # cruiser
				$findableShips[207] = [210, 202, 204, 203, 205, 206, 207, 215]; # battleship
				$findableShips[215] = [210, 202, 204, 203, 205, 206, 207, 215, 211]; # battle cruiser
				$findableShips[211] = [210, 202, 204, 203, 205, 206, 207, 215, 211, 213]; # planet bomber
				$findableShips[213] = [210, 202, 204, 203, 205, 206, 207, 215, 211, 213]; # destroyer

				$highestShipId = 0;
				if (array_key_exists(210, $fleetArray))
					$highestShipId = 210;
				if (array_key_exists(202, $fleetArray))
					$highestShipId = 202;
				if (array_key_exists(204, $fleetArray))
					$highestShipId = 204;
				if (array_key_exists(203, $fleetArray))
					$highestShipId = 203;
				if (array_key_exists(205, $fleetArray))
					$highestShipId = 205;
				if (array_key_exists(206, $fleetArray))
					$highestShipId = 206;
				if (array_key_exists(207, $fleetArray))
					$highestShipId = 207;
				if (array_key_exists(215, $fleetArray))
					$highestShipId = 215;
				if (array_key_exists(211, $fleetArray))
					$highestShipId = 211;
				if (array_key_exists(213, $fleetArray))
					$highestShipId = 213;

				$Found			= array();
				$upperValue = 3;
				while($highestShipId > 0 && $upperValue > 0)
				{
					$ID = $findableShips[$highestShipId][rand(0, count($findableShips[$highestShipId])-1)];
					$MaxFound = floor($FoundShips / ($pricelist[$ID]['cost'][901] + $pricelist[$ID]['cost'][902]));
					if($MaxFound <= 0)
					{
						$upperValue -= 1;
						continue;
					}

					$Count = mt_rand(0, $MaxFound);
					if($Count <= 0)
					{
						$upperValue -= 1;
						continue;
					}

					if(array_key_exists($ID, $Found))
						$Found[$ID] += $Count;
					else
						$Found[$ID] = $Count;
					$FoundShips -= $Count * ($pricelist[$ID]['cost'][901] + $pricelist[$ID]['cost'][902]);
					if($FoundShips <= 0)
						break;
				}

				if (empty($Found)) {
					$FoundShipMess .= '<br><br>'.$LNG['sys_expe_found_ships_nothing'];
				}

				foreach($reslist['fleet'] as $ID)
				{
					$Count = 0;
					if(!empty($Found[$ID]))
					{
						$Count += $Found[$ID];
						$FoundShipMess .= '<br>'.$LNG['tech'][$ID].': '.pretty_number($Count);
					}
					if(!empty($fleetArray[$ID]))
					{
						$Count += $fleetArray[$ID];
					}

					if ($Count > 0)
					{
						$NewFleetArray .= $ID.",".floatToString($Count).';';
					}
				}

				$Message .= $FoundShipMess;

				$this->UpdateFleet('fleet_array', $NewFleetArray);
				$this->UpdateFleet('fleet_amount', array_sum($fleetArray));
			}

			// Find pirates or aliens: 8,4% - 5.8% pirates or 2.6% aliens.
			elseif ($GetEvent < 719)
			{

				if (!$config->expedition_allow_expedition_war) { break; }

				$messageHTML	= <<<HTML
	<div class="raportMessage">
	<table>
	<tr>
	<td colspan="2"><a href="CombatReport.php?raport=%s" target="_blank"><span class="%s">%s %s (%s)</span></a></td>
	</tr>
	<tr>
	<td>%s</td><td><span class="%s">%s: %s</span>&nbsp;<span class="%s">%s: %s</span></td>
	</tr>
	<tr>
	<td>%s</td><td><span>%s:&nbsp;<span class="raportSteal element901">%s</span>&nbsp;</span><span>%s:&nbsp;<span class="raportSteal element902">%s</span>&nbsp;</span><span>%s:&nbsp;<span class="raportSteal element903">%s</span></span></td>
	</tr>
	<tr>
	<td>%s</td><td><span>%s:&nbsp;<span class="raportDebris element901">%s</span>&nbsp;</span><span>%s:&nbsp;<span class="raportDebris element902">%s</span></span></td>
	</tr>
	</table>
	</div>
	HTML;
				//Minize HTML
				$messageHTML	= str_replace(array("\n", "\t", "\r"), "", $messageHTML);

				// pirate or alien
				$attackType	= mt_rand(1, 84);
				$eventSize	= mt_rand(0, 100);

				$targetFleetData	= array();

				// pirates
				if($attackType <= 58)
				{
					$techBonus		= -3;
					$targetName		= $LNG['sys_expe_attackname_1'];
					$roundFunction	= 'floor';

					if(10 < $eventSize)
					{
						$Message    			= $LNG['sys_expe_attack_1_1_'.rand(1, 5)];
						$attackFactor			= (30 + mt_rand(-3, 3)) / 100;
						$targetFleetData[204]	= 5;
					}
					elseif(0 < $eventSize && 10 >= $eventSize)
					{
						$Message    			= $LNG['sys_expe_attack_1_2_'.rand(1, 3)];
						$attackFactor			= (50 + mt_rand(-5, 5)) / 100;
						$targetFleetData[206]	= 3;
					}
					else
					{
						$Message   				= $LNG['sys_expe_attack_1_3_'.rand(1, 3)];
						$attackFactor			= (80 + mt_rand(-8, 8)) / 100;
						$targetFleetData[207]	= 2;
					}
				}
				else // aliens
				{
					$techBonus		= 3;
					$targetName		= $LNG['sys_expe_attackname_2'];
					$roundFunction	= 'ceil';

					if(10 < $eventSize)
					{
						$Message    			= $LNG['sys_expe_attack_2_1_'.rand(1, 4)];
						$attackFactor			= (40 + mt_rand(-4, 4)) / 100;
						$targetFleetData[205]	= 5;
					}
					elseif(0 < $eventSize && 10 >= $eventSize)
					{
						$Message    			= $LNG['sys_expe_attack_2_2_'.rand(1, 3)];
						$attackFactor			= (60 + mt_rand(-6, 6)) / 100;
						$targetFleetData[215]	= 3;
					}
					else
					{
						$Message    			= $LNG['sys_expe_attack_2_3_'.rand(1, 3)];
						$attackFactor			= (90 + mt_rand(-9, 9)) / 100;
						$targetFleetData[213]	= 2;
					}
				}

				foreach($fleetArray as $shipId => $shipAmount)
				{
					if(!isset($targetFleetData[$shipId]))
					{
						$targetFleetData[$shipId] = 0;
					}

					$targetFleetData[$shipId] += $roundFunction($shipAmount * $attackFactor);
				}

				$targetFleetData	= array_filter($targetFleetData);

				$sql = 'SELECT * FROM %%USERS%% WHERE id = :userId;';

				$senderData	= Database::get()->selectSingle($sql, array(
					':userId'	=> $this->_fleet['fleet_owner']
				));

				$targetData	= array(
					'id'			=> 0,
					'username'		=> $targetName,
					'military_tech'	=> max($senderData['military_tech'] + $techBonus, 0),
					'defence_tech'	=> max($senderData['defence_tech'] + $techBonus, 0),
					'shield_tech'	=> max($senderData['shield_tech'] + $techBonus, 0),
					'rpg_amiral'	=> 0,
					'dm_defensive'	=> 0,
					'dm_attack' 	=> 0
				);

				$fleetID	= $this->_fleet['fleet_id'];

				$fleetAttack[$fleetID]['fleetDetail']		= $this->_fleet;
				$fleetAttack[$fleetID]['player']			= $senderData;
				$fleetAttack[$fleetID]['player']['factor']	= getFactors($fleetAttack[$this->_fleet['fleet_id']]['player'], 'attack', $this->_fleet['fleet_start_time']);
				$fleetAttack[$fleetID]['unit']				= $fleetArray;

				$fleetDefend = array();

				$fleetDefend[0]['fleetDetail'] = array(
					'fleet_start_galaxy'		=> $this->_fleet['fleet_end_galaxy'],
					'fleet_start_system'		=> $this->_fleet['fleet_end_system'],
					'fleet_start_planet'		=> $this->_fleet['fleet_end_planet'],
					'fleet_start_type'			=> 1,
					'fleet_end_galaxy'			=> $this->_fleet['fleet_end_galaxy'],
					'fleet_end_system'			=> $this->_fleet['fleet_end_system'],
					'fleet_end_planet'			=> $this->_fleet['fleet_end_planet'],
					'fleet_end_type'			=> 1,
					'fleet_resource_metal'		=> 0,
					'fleet_resource_crystal'	=> 0,
					'fleet_resource_deuterium'	=> 0
				);

				$bonusList	= BuildFunctions::getBonusList();

				$fleetDefend[0]['player']	= $targetData;
				$fleetDefend[0]['player']['factor']	= ArrayUtil::combineArrayWithSingleElement($bonusList, 0);
				$fleetDefend[0]['unit']		= $targetFleetData;

				require_once 'includes/classes/missions/functions/calculateAttack.php';
				$combatResult	= calculateAttack($fleetAttack, $fleetDefend, $config->Fleet_Cdr, $config->Defs_Cdr);

				$fleetArray = '';
				$totalCount = 0;

				$fleetAttack[$fleetID]['unit']	= array_filter($fleetAttack[$fleetID]['unit']);
				foreach ($fleetAttack[$fleetID]['unit'] as $element => $amount)
				{
					$fleetArray .= $element.','.$amount.';';
					$totalCount += $amount;
				}

				if ($totalCount <= 0)
				{
					$this->KillFleet();
				}
				else
				{
					$this->UpdateFleet('fleet_array', substr($fleetArray, 0, -1));
					$this->UpdateFleet('fleet_amount', $totalCount);
					$fleetArray = FleetFunctions::unserialize($fleetArray);
				}

				require_once('includes/classes/missions/functions/GenerateReport.php');


				$debrisResource	= array(901, 902);
				$debris			= array();

				foreach($debrisResource as $elementID)
				{
					$debris[$elementID]			= 0;
				}

				$stealResource	= array(901 => 0, 902 => 0, 903 => 0);

				$reportInfo	= array(
					'thisFleet'				=> $this->_fleet,
					'debris'				=> $debris,
					'stealResource'			=> $stealResource,
					'moonChance'			=> 0,
					'moonDestroy'			=> false,
					'moonName'				=> NULL,
					'moonDestroyChance'		=> NULL,
					'moonDestroySuccess'	=> NULL,
					'fleetDestroyChance'	=> NULL,
					'fleetDestroySuccess'	=> NULL,
				);

				$reportData	= GenerateReport($combatResult, $reportInfo);

				$reportID	= md5(uniqid('', true).TIMESTAMP);

				$sql		= "INSERT INTO %%RW%% SET
				rid			= :reportId,
				raport		= :reportData,
				time		= :time,
				attacker	= :attacker;";

				Database::get()->insert($sql, array(
					':reportId'		=> $reportID,
					':reportData'	=> serialize($reportData),
					':time'			=> $this->_fleet['fleet_start_time'],
					':attacker'		=> $this->_fleet['fleet_owner'],
				));

				switch($combatResult['won'])
				{
					case "a":
						$attackClass	= 'raportWin';
						$defendClass	= 'raportLose';
					break;
					case "r":
						$attackClass	= 'raportLose';
						$defendClass	= 'raportWin';
					break;
					default:
						$attackClass	= 'raportDraw';
						$defendClass	= 'raportDraw';
					break;
				}

				$message	= sprintf($messageHTML,
					$reportID,
					$attackClass,
					$LNG['sys_mess_attack_report'],
					sprintf(
						$LNG['sys_adress_planet'],
						$this->_fleet['fleet_end_galaxy'],
						$this->_fleet['fleet_end_system'],
						$this->_fleet['fleet_end_planet']
					),
					$LNG['type_planet_short_'.$this->_fleet['fleet_end_type']],
					$LNG['sys_lost'],
					$attackClass,
					$LNG['sys_attack_attacker_pos'], pretty_number($combatResult['unitLost']['attacker']),
					$defendClass,
					$LNG['sys_attack_defender_pos'], pretty_number($combatResult['unitLost']['defender']),
					$LNG['sys_gain'],
					$LNG['tech'][901], pretty_number($stealResource[901]),
					$LNG['tech'][902], pretty_number($stealResource[902]),
					$LNG['tech'][903], pretty_number($stealResource[903]),
					$LNG['sys_debris'],
					$LNG['tech'][901], pretty_number($debris[901]),
					$LNG['tech'][902], pretty_number($debris[902])
				);

				PlayerUtil::sendMessage($this->_fleet['fleet_owner'], 0, $LNG['sys_mess_tower'], 3,
					$LNG['sys_mess_attack_report'], $message, $this->_fleet['fleet_end_stay']);
			}

			// Black hole: 0,3%
			elseif ($GetEvent < 722)
			{

				if (!$config->expedition_allow_fleet_loss) { break;}

					$this->KillFleet();
					$Message	= $LNG['sys_expe_lost_fleet_'.mt_rand(1,4)];

			}

			// The fleet delays or return earlier: 9%
			elseif ($GetEvent < 812)
			{
				# http://owiki.de/Expedition#Ver.C3.A4nderte_Flugzeit
				$chance	= mt_rand(0, 100);

				$Wrapper	= array();
				$Wrapper[]	= 2;
				$Wrapper[]	= 2;
				$Wrapper[]	= 2;
				$Wrapper[]	= 2;
				$Wrapper[]	= 2;
				$Wrapper[]	= 2;
				$Wrapper[]	= 2;
				$Wrapper[]	= 3;
				$Wrapper[]	= 3;
				$Wrapper[]	= 5;

				$normalBackTime	= $this->_fleet['fleet_end_time'] - $this->_fleet['fleet_end_stay'];
				$stayTime		= $this->_fleet['fleet_end_stay'] - $this->_fleet['fleet_start_time'];
				$factor			= $Wrapper[mt_rand(0, 9)];

				if($chance < 75)
				{
					if(!$config->expedition_allow_fleet_delay){ break; }
					// More return time
					$endTime = $this->_fleet['fleet_end_stay'] + $normalBackTime + $stayTime * $factor;
					$this->UpdateFleet('fleet_end_time', $endTime);
					$Message = $LNG['sys_expe_time_slow_'.mt_rand(1,6)];
				}
				else
				{
					if (!$config->expedition_allow_fleet_speedup) { break; }
					// Less return time
					$endTime = $this->_fleet['fleet_end_stay'] + max(1, $normalBackTime - $stayTime / 3 * $factor);
					$this->UpdateFleet('fleet_end_time', $endTime);
					$Message = $LNG['sys_expe_time_fast_'.mt_rand(1,3)];
				}
			}

		} while (0);


		// else
		// Find nothing: 18,8%...

		if(isset($fleetArray[210]))
			$Message .= '<br><br>'.$logbook;

		PlayerUtil::sendMessage($this->_fleet['fleet_owner'], 0, $LNG['sys_mess_tower'], 15,
			$LNG['sys_expe_report'], $Message, $this->_fleet['fleet_end_stay'], NULL, 1, $this->_fleet['fleet_universe']);

		$this->setState(FLEET_RETURN);
		$this->SaveFleet();
	}

	function ReturnEvent()
	{
		$LNG		= $this->getLanguage(NULL, $this->_fleet['fleet_owner']);
		$Message 	= sprintf(
			$LNG['sys_expe_back_home'],
			$LNG['tech'][901], pretty_number($this->_fleet['fleet_resource_metal']),
			$LNG['tech'][902], pretty_number($this->_fleet['fleet_resource_crystal']),
			$LNG['tech'][903], pretty_number($this->_fleet['fleet_resource_deuterium']),
			$LNG['tech'][921], pretty_number($this->_fleet['fleet_resource_darkmatter'])
		);

		PlayerUtil::sendMessage($this->_fleet['fleet_owner'], 0, $LNG['sys_mess_tower'], 4, $LNG['sys_mess_fleetback'],
			$Message, $this->_fleet['fleet_end_time'], NULL, 1, $this->_fleet['fleet_universe']);

		$this->RestoreFleet();
	}
}
