<?php

namespace App\Parsers\GE;

use App\Parsers\CsvParseTrait;
use App\Parsers\ParseInterface;
use Symfony\Component\Console\Helper\ProgressBar;

class Quests implements ParseInterface
{
    use CsvParseTrait;

    // the wiki format we shall use
    const WIKI_FORMAT = '
        {{ARR Infobox Quest
        |Patch = {patch}
        |Index = {id}
        |Name = {name}{types}{repeatable}{faction}{eventicon}{reputationrank}
        {smallimage}
        |Level = {level}
        {requiredclass}
        |Required Affiliation =
        |Quest Number ={instancecontent1}{instancecontent2}{instancecontent3}

        |Required Quests ={prevquest1}{prevquest2}{prevquest3}
        |Unlocks Quests =

        |Objectives =
        {objectives}
        |Description =
        {expreward}{gilreward}{sealsreward}
        {tomestones}{relations}{instanceunlock}{questrewards}{catalystrewards}{guaranteeditem7}{guaranteeditem8}{guaranteeditem9}{guaranteeditem11}{questoptionrewards}
        
        |Issuing NPC = {questgiver}
        |NPC Location =
        
        |NPCs Involved =
        |Mobs Involved =
        |Items Involved =
        
        |Journal =
        {journal}
        
        |Strategy =
        |Walkthrough =
        |Dialogue =
        |Etymology =
        |Images =
        |Notes =
        }}
        http://ffxiv.gamerescape.com/wiki/Loremonger:{name}?action=edit
        <noinclude>{{Lorempageturn|prev={prevquest1}|next=}}{{Loremquestheader|{name}|Mined=X|Summary=}}</noinclude>
        {{LoremLoc|Location=}}
        {dialogue}';

    public function parse()
    {
        // i should pull this from xivdb :D
        $patch = '4.35';

        // grab CSV files
        $questCsv = $this->csv('Quest');
        $ENpcResidentCsv = $this->csv('ENpcResident');
        $ItemCsv = $this->csv('Item');
        $EmoteCsv = $this->csv('Emote');
        $JournalGenreCsv = $this->csv('JournalGenre');
        $JournalCategoryCsv = $this->csv('JournalCategory');
        $JournalSectionCsv = $this->csv('JournalSection');
        $PlaceNameCsv = $this->csv('PlaceName');
        $ClassJobCsv = $this->csv('ClassJob');
        $ActionCsv = $this->csv('Action');
        $OtherRewardCsv = $this->csv('QuestRewardOther');
        $InstanceContentCsv = $this->csv('InstanceContent');
        $BeastReputationRankCsv = $this->csv('BeastReputationRank');
        $BeastTribeCsv = $this->csv('BeastTribe');
        $TraitCsv = $this->csv('Trait');

        $this->io->progressStart($questCsv->total);

        // loop through quest data
        foreach($questCsv->data as $id => $quest) {
            //print_r(array_keys($quest));die;
            // ---------------------------------------------------------
            $this->io->progressAdvance();

            // skip ones without a name
            if (empty($quest['Name'])) {
                continue;
            }

            //---------------------------------------------------------------------------------
            //---------------------------------------------------------------------------------
            //---------------------------------------------------------------------------------
            //---------------------------------------------------------------------------------
            //---------------------------------------------------------------------------------
            //---------------------------------------------------------------------------------

            // change tomestone number to wiki parameter name
            $tomestoneList = [
                1 => '|ARRTomestone = ',
                2 => '|TomestoneLow = ',
                3 => '|TomestoneHigh = ',
            ];

            $TraitReward = $TraitCsv->find("Quest", $quest["id"]);
            print_r(array_values($TraitReward));
            //echo "Reward = $TraitReward\n";

            // Loop through guaranteed QuestRewards and display the Item Name
            $questRewards = [];
            $RewardNumber = false;
            foreach(range(0,5) as $i) {
                $guaranteeditemname = $ItemCsv->at($quest["Item{Reward}[0][{$i}]"])['Name'];
                if ($quest["ItemCount{Reward}[0][{$i}]"] > 0) {
                    $RewardNumber = ($RewardNumber + 1);
                    $string = "\n\n|QuestReward ". $RewardNumber ." = ". $guaranteeditemname;

                    // Show the Qty if more than 1 is received.
                    if ($quest["ItemCount{Reward}[0][{$i}]"] > 1) {
                        $string .= "\n|QuestReward ". $RewardNumber ." Count = ". $quest["ItemCount{Reward}[0][{$i}]"] ."\n";
                    }

                    $questRewards[] = $string;
                }
            }

            $questRewards = implode("\n", $questRewards);

            // Loop through catalyst rewards and display the Item Name as QuestReward #.
            $catalystRewards = [];
            foreach(range(0,2) as $i) {
                $guaranteedcatalystname = $ItemCsv->at($quest["Item{Catalyst}[{$i}]"])['Name'];
                if ($quest["ItemCount{Catalyst}[{$i}]"] > 0) {
                    $RewardNumber = ($RewardNumber + 1);
                    $string = "\n|QuestReward ". $RewardNumber ." = ". $guaranteedcatalystname;

                    // show Catalyst Qty received if greater than 1
                    if ($quest["ItemCount{Catalyst}[{$i}]"] > 1) {
                        $string .= "\n|QuestReward ". $RewardNumber ." Count = ". $quest["ItemCount{Catalyst}[{$i}]"] ."\n";
                    }

                    $catalystRewards[] = $string;
                }
            }

            $catalystRewards = implode("\n", $catalystRewards);

            // Loop through optional quest rewards and display them, as QuestRewardOption #.
            $questoptionRewards = [];
            foreach(range(0,4) as $i) {
                $optionalitemname = $ItemCsv->at($quest["Item{Reward}[1][{$i}]"])['Name'];

                // if optional item count is greater than zero, show the reward.
                if ($quest["ItemCount{Reward}[1][{$i}]"] > 0) {
                    $RewardNumber = ($RewardNumber + 1);
                    $string = "\n|QuestRewardOption ". $RewardNumber ." = ". $optionalitemname;

                    // If Qty is greater than 1, show Qty.
                    if ($quest["ItemCount{Reward}[1][{$i}]"] > 1) {
                        $string .= "\n|QuestRewardOption ". $RewardNumber ." Count = ". $quest["ItemCount{Reward}[1][{$i}]"] ."\n";
                    }

                    // If reward is HQ, show HQ.
                    if ($quest["IsHQ{Reward}[1][{$i}]"] === "True") {
                        $string .= "\n|QuestRewardOption ". $RewardNumber ." HQ = x\n";
                    }

                    $questoptionRewards[] = $string;
                }
            }
            $questoptionRewards = implode("\n", $questoptionRewards);

            // If Emote is received as reward, display Emote Name from Emote.csv
            $guaranteedreward7 = false;
            if ($quest['Emote{Reward}']) {
                $RewardNumber = ($RewardNumber + 1);
                $emoterewardname = $EmoteCsv->at($quest["Emote{Reward}"])['Name'];
                $string = "\n|QuestReward ". $RewardNumber ." = ". $emoterewardname;
                $guaranteedreward7 = $string;
            }

            // If Class/Job Action is rewarded, display Action Name from Action.csv
            $guaranteedreward8 = false;
            if ($quest['Action{Reward}']) {
                $RewardNumber = ($RewardNumber + 1);
                $ActionRewardName = $ActionCsv->at($quest['Action{Reward}'])['Name'];
                $string = "\n|QuestReward ". $RewardNumber ." = ". $ActionRewardName;
                $guaranteedreward8 = $string;
            }

            //If General Action is rewarded, display Action Name from Action.csv (different from above)
            $guaranteedreward9 = [];
            foreach(range(0,1) as $i) {
                if ($quest["GeneralAction{Reward}[{$i}]"] > 0){
                    $RewardNumber = ($RewardNumber + 1);
                    $GeneralActionRewardName = $ActionCsv->at($quest["GeneralAction{Reward}[{$i}]"])['Name'];
                    $string = "\n|QuestReward ". $RewardNumber ." = ". $GeneralActionRewardName;
                    $guaranteedreward9[] = $string;
                }
            }

            $guaranteedreward9 = implode("\n", $guaranteedreward9);

            // If "Other Reward" is received, then show Other Name from OtherReward.csv
            $guaranteedreward11 = false;
            if ($quest['Other{Reward}']) {
                $RewardNumber = ($RewardNumber + 1);
                $OtherRewardName = $OtherRewardCsv->at($quest['Other{Reward}'])['Name'];
                $string = "\n|QuestReward ". $RewardNumber ." = ". $OtherRewardName;
                $guaranteedreward11 = $string;
            }



            // If Event Icon greater than 0 (not blank) then display it in an html comment.
            // Need to update this later with a switch for the various Events.
            $eventicon = false;
            if ($quest['Icon{Special}'] > 0) {
                $string = "\n|Event = <!-- ui/icon/080000/". $quest['Icon{Special}'] .".tex -->";
                $eventicon = $string;
            }

            // If Small Image (Quest Header Image) is greater than 0 (not blank), then display in html comment
            // with "Quest Name Image.png" as default location for the filename to be saved.
            $smallimage = false;
            if ($quest['Icon'] > 0) {
                $string = "\n|SmallImage = ". $quest['Name'] ." Image.png <!-- ui/icon/100000/". $quest['Icon'] .".tex -->";
                $smallimage = $string;
            }

            // If Beast Tribe Action is defined, show it. Otherwise don't.
            $faction = false;
            if ($quest['BeastTribe']) {
                $string = "\n|Faction = ". ucwords(strtolower($BeastTribeCsv->at($quest['BeastTribe'])['Name']));
                $faction = $string;
            }

            // If "Beast Tribe Reputation Required" equals "None", don't display. Otherwise show it.
            // Used for Beast quests that have a specific Reputation required to receive Quest.
            $reputation = false;
            if ($BeastReputationRankCsv->at($quest['BeastReputationRank'])['Name'] === "None") {
            } else {
                $string = "\n|Required Reputation = ". $BeastReputationRankCsv->at($quest['BeastReputationRank'])['Name'];
                $reputation = $string;
            }

            // If Beast Tribe Currency is received, show it.
            $relations = false;
            if ($quest['ReputationReward'] > 0) {
                $string = "\n|Relations = ". $quest['ReputationReward'];
                $relations = $string;
            }

            // If you unlock a Dungeon during this quest, show the Name.
            $instanceunlock = false;
            if ($quest['InstanceContent{Unlock}']) {
                $string = "\n|Misc Reward = [[". $InstanceContentCsv->at($quest['InstanceContent{Unlock}'])['Name'] ."]] unlocked.";
                $instanceunlock = $string;
            }

            // Display Grand Company Seal Reward if it's more than zero.
            $sealsreward = false;
            if ($quest['GCSeals'] > 0) {
                $string = "\n|SealsReward = ". $quest['GCSeals'];
                $sealsreward = $string;
            }

            // don't display Required Class if equal to "adventurer". Otherwise, show its proper/capitalized Name.
            $requiredclass = false;
            if ($ClassJobCsv->at($quest['ClassJob{Required}'])['Name{English}'] === "Adventurer") {
            } else {
                $string = "\n|Required Class = ". $ClassJobCsv->at($quest['ClassJob{Required}'])['Name{English}'];
                $requiredclass = $string;
            }

            // Show GilReward if more than zero. Otherwise, blank it.
            if ($quest['GilReward'] > 0) {
                $string = "\n|GilReward = ". $quest['GilReward'];
                $gilreward = $string;
            } else {
                $string = "\n|GilReward =";
                $gilreward = $string;
            }

            // Show EXPReward if more than zero. Otherwise, blank it.
            if ($this->getQuestExp($quest) > 0) {
                $string = "\n|EXPReward = ". $this->getQuestExp($quest);
                $expreward = $string;
            } else {
                $string = "\n|EXPReward =";
                $expreward = $string;
            }

            //Stores entire row of JournalGenre in $JournalGenre
            $JournalGenreRow = $JournalGenreCsv->at($quest['JournalGenre']);
            //^^^ 53,61411,29,"La Noscean Sidequests"

            //In Quest.csv, take the raw number from Index:JournalGenre and convert it into an actual Name by
            //looking inside the JournalGenre.csv file and returning the Index:Name for its entry.
            $JournalGenreName = $JournalGenreCsv->at($quest['JournalGenre'])['Name'];
            //^^^ La Noscean Sidequests

            //Stores entire row of JournalGenreCategory in $JournalGenreCategory
            $JournalCategoryRow = $JournalCategoryCsv->at($JournalGenreRow['JournalCategory']);
            //^^^ 29,"Lominsan Sidequests",3,1,3

            //Show the Index # of the JournalCategory.csv file
            $JournalCategoryNumber = $JournalCategoryCsv->at($JournalGenreRow['JournalCategory'])['id'];
            //^^^ 29

            //Take the same row from $JournalGenreName (JournalGenre.csv) and, using the information found at
            //the 'JournalCategory' index for $JournalGenreName, return the 'Name' index for that number from the
            //JournalCategory.csv file.
            $JournalCategoryName = $JournalCategoryCsv->at($JournalGenreRow['JournalCategory'])['Name'];
            //^^^ Lominsan Sidequests

            $JournalSectionRow = $JournalSectionCsv->at($JournalCategoryRow['JournalSection']);
            //^^^ 3,"Sidequests",True,True

            //Take the same row from $JournalGenreCategory (JournalCategory.csv) and, using the information found at
            //the 'JournalSection' index for $JournalGenreCategory, return the 'Name' index for that number from the
            //JournalSection.csv file.
            $JournalSectionName = $JournalSectionCsv->at($JournalCategoryRow['JournalSection'])['Name'];
            //^^^ Sidequests

            //Show the Index # of the JournalSection.csv file
            $JournalSectionNumber = $JournalSectionCsv->at($JournalCategoryRow['JournalSection'])['id'];
            //^^^ 3

            // If SectionName is MSQ, or one of the Beast Tribe Names, show Section and Type.
            if ($JournalSectionName == "Main Scenario (ARR/Heavensward)" || $JournalSectionName == "Main Scenario (Stormblood)"
                || $JournalSectionName == "Chronicles of a New Era") {
                $string = "\n|Section = ". $JournalSectionName;
                $string .= "\n|Type = ". $JournalCategoryName;
                $types = $string;

                // If JournalSectionName is for Beast Tribes, then show Section, Type, and subtype
            } elseif ($JournalSectionName == "Beast Tribe Quests (ARR/Heavensward)" || $JournalSectionName
                == "Beast Tribe Quests (Stormblood)") {
                $string = "\n|Section = ". $JournalSectionName;
                $string .= "\n|Type = ". $JournalCategoryName;
                $string .= "\n|Subtype = ".$JournalGenreName;
                $types = $string;

                // Else if $JournalSectionName is Sidequests and $JournalCategoryName is not equal to Side Story Quests
                // show Type, Subtype, and Subtype2 (Placename for SubType2 since all Sidequests except SSQ show it.)
            } elseif ($JournalSectionName === "Sidequests" && $JournalCategoryName != "Side Story Quests") {
                $QuestPlaceName = $PlaceNameCsv->at($quest['PlaceName'])['Name'];
                $string = "\n|Type = " . $JournalSectionName;
                $string .= "\n|Subtype = " . $JournalCategoryName;
                $string .= "\n|Subtype2 = " . $QuestPlaceName;
                $types = $string;

                // If Other quests (GC, Seasonal, Special quests) then show specific order.
            } elseif ($JournalSectionName === "Other Quests") {
                $string = "\n|Section = ". $JournalSectionName;
                $string .= "\n|Type = ". $JournalCategoryName;
                $string .= "\n|Subtype = ". $JournalGenreName;
                $types = $string;

                // Otherwise, for everything else (Job quests, Levequests, etc) show Section, Type, and Subtype correctly.
            } else {
                $string = "\n|Section = ". $JournalSectionName;
                $string .= "\n|Type = ". $JournalGenreName;
                $string .= "\n|Subtype = ". $JournalSectionName;
                $types = $string;
            }

            // Show Repeatable as 'Yes' for instantly repeatable quests, or 'Daily' for dailies, or none
            $repeatable = false;
            if (($quest['IsRepeatable'] === "True") && ($quest['RepeatIntervalType'] == 1)) {
                $string = "\n|Repeatable = Daily";
                $repeatable = $string;
            } elseif (($quest['IsRepeatable'] === "True") && ($quest['RepeatIntervalType'] == 0)) {
                $string = "\n|Repeatable = Yes";
                $repeatable = $string;
            }

            //Show the Previous Quest(s) correct Name by looking them up.
            $prevquest1 = $questCsv->at($quest['PreviousQuest[0]'])['Name'];
            $prevquest2 = $questCsv->at($quest['PreviousQuest[1]'])['Name'];
            $prevquest3 = $questCsv->at($quest['PreviousQuest[2]'])['Name'];

            // npc start + finish name
            $questgiver = ucwords(strtolower($ENpcResidentCsv->at($quest['ENpcResident{Start}'])['Singular']));

            //---------------------------------------------------------------------------------

            $data = [
                '{patch}' => $patch,
                '{id}' => $quest['id'],
                '{name}' => $quest['Name'],
                '{types}' => $types,
                //'{questicontype}' => $npcIconAvailable,
                '{eventicon}' => $eventicon,
                '{smallimage}' => $smallimage,
                '{level}' => $quest['ClassJobLevel[0]'],
                '{reputationrank}' => $reputation,
                '{repeatable}' => $repeatable,
                //'{interval}' => $quest['repeat_interval_type,']
                '{faction}' => $faction,
                '{requiredclass}' => $requiredclass,
                '{instancecontent1}' => $quest['InstanceContent[0]'] ? "|Dungeon Requirement = ". $quest['InstanceContent[0]'] : "",
                '{instancecontent2}' => $quest['InstanceContent[1]'] ? ", ". $quest['InstanceContent[1]'] : "",
                '{instancecontent3}' => $quest['InstanceContent[2]'] ? ", ". $quest['InstanceContent[2]'] : "",
                '{prevquest1}' => $prevquest1 ? $prevquest1 : "",
                '{prevquest2}' => $prevquest2 ? ", ". $prevquest2 : "",
                '{prevquest3}' => $prevquest3 ? ", ". $prevquest3 : "",
                '{expreward}' => $expreward,
                '{gilreward}' => $gilreward,
                '{sealsreward}' => $sealsreward,
                '{tomestones}' => $quest['TomestoneCount{Reward}'] ? $tomestoneList[$quest['Tomestone{Reward}']] . $quest['TomestoneCount{Reward}'] : '',
                '{relations}' => $relations,
                '{instanceunlock}' => $instanceunlock,
                '{questrewards}' => $questRewards,
                '{catalystrewards}' => $catalystRewards,
                '{guaranteeditem7}' => $guaranteedreward7,
                '{guaranteeditem8}' => $guaranteedreward8,
                '{guaranteeditem9}' => $guaranteedreward9,
                '{guaranteeditem11}' => $guaranteedreward11,
                '{questoptionrewards}' => $questoptionRewards,
                '{questgiver}' => $questgiver,
                //'{journal}' => implode("\n", $journal),
                //'{objectives}' => implode("\n",  $objectives),
                //'{dialogue}' => implode("\n", $dialogue),
                //'{traits}' => $TraitRow,
            ];

//            echo "
//JournalGenreName:         {$JournalGenreName}
//JournalCategoryName:      {$JournalCategoryName}
//JournalSectionName:       {$JournalSectionName}
//JournalCategoryNumber:    {$JournalCategoryNumber}
//JournalSectionNumber:     {$JournalSectionNumber}\n";

            // format using GamerEscape Formater and add to data array
            $this->data[] = GeFormatter::format(self::WIKI_FORMAT, $data);
        }

        // save our data to the filename: GeQuestWiki.txt
        $this->io->progressFinish();
        $this->io->text('Saving ...');
        $info = $this->save('GeQuestWiki.txt');

        $this->io->table(
            [ 'Filename', 'Data Count', 'File Size' ],
            $info
        );
    }

    private function getQuestExp($quest)
    {
        $paramGrow  = $this->csv("ParamGrow")->at($quest['ClassJobLevel[0]']);

        // Base EXP (1-49)
        $EXP = $quest['ExpFactor'] * $paramGrow['QuestExpModifier'] * (45 + (5 * $quest['ClassJobLevel[0]'])) / 100;

        // Quest lv 50
        if (in_array($quest['ClassJobLevel[0]'], [50])) {
            $EXP = $EXP + ((400 * ($quest['ExpFactor'] / 100)) + (($quest['ClassJobLevel[0]'] - 50) * (400 * ($quest['ExpFactor'] / 100))));
        }

        // Quest lv 51
        else if (in_array($quest['ClassJobLevel[0]'], [51])) {
            $EXP = $EXP + ((800 * ($quest['ExpFactor'] / 100)) + (($quest['ClassJobLevel[0]'] - 50) * (400 * ($quest['ExpFactor'] / 100))));
        }

        // Quest lv 52-59
        else if (in_array($quest['ClassJobLevel[0]'], [52,53,54,55,56,57,58,59])) {
            $EXP = $EXP + ((2000  * ($quest['ExpFactor'] / 100)) + (($quest['ClassJobLevel[0]'] - 52) * (2000  * ($quest['ExpFactor'] / 100))));
        }

        // Quest EXP 60-69
        else if (in_array($quest['ClassJobLevel[0]'], [60,61,62,63,64,65,66,67,68,69])) {
            $EXP = $EXP + ((37125  * ($quest['ExpFactor'] / 100)) + (($quest['ClassJobLevel[0]'] - 60) * (3375  * ($quest['ExpFactor'] / 100))));
        }

        return $EXP;
    }
}
