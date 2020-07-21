<?php

namespace App\Parsers\GE;

use App\Parsers\CsvParseTrait;
use App\Parsers\ParseInterface;

/**
 * php bin/console app:parse:csv GE:FishParameter
 */
class Sightseeing implements ParseInterface
{
    use CsvParseTrait;

    // the wiki output format / template we shall use
    const WIKI_FORMAT = "{{-start-}}
'''Sightseeing Log: {name}'''
{{ARR Infobox Sightseeing Log
| Patch = {patch}
| Expansion   = Shadowbringers
| Name        = {name}
| Location    = {name}
| Coordinates = {{Information Needed}}
| Vista Record Number = 0{number}
| Impression = \"{impression}\"

| Description = {description}
| Walkthrough =
| Weather  =
| Emote = {emote}
| Time ={time}
| Miscellaneous Requirement =

| Map =
| Map Description  =

| Screenshot =
| Screenshot Description =

| Vista Image = Sightseeing Log - SHB0{number}-Complete.png
| Vista Image Description =

| Icon = {name} Image.png
}}{{-stop-}}";
    public function parse()
    {
        include (dirname(__DIR__) . '/Paths.php');

        // grab CSV files we want to use
        $AdventureCsv = $this->csv('Adventure');
        $EmoteCsv = $this->csv('Emote');

        // (optional) start a progress bar
        $this->io->progressStart($AdventureCsv->total);

        // loop through data
        foreach ($AdventureCsv->data as $id => $item) {
            $this->io->progressAdvance();

            $name2 = str_replace("<Emphasis>", "", $item['Name']);
            $name = str_replace("</Emphasis>", "", $name2);
            $emote = $EmoteCsv->at($item['Emote'])['Name'];
            $number = ($id-2162687-204);

            // ensure output directory exists
            $IconoutputDirectory = $this->getOutputFolder() ."/$CurrentPatchOutput/SightseeingLogIcons";
            if (!is_dir($IconoutputDirectory)) {
                mkdir($IconoutputDirectory, 0777, true);
            }

            // build icon input folder paths
            $itemIconsmall = $this->getInputFolder() .'/icon/'. $this->iconize($item['Icon{List}']);
            $itemIconlarge = $this->getInputFolder() .'/icon/'. $this->iconize($item['Icon{Discovered}']);

            $iconFileName = "$IconoutputDirectory/small/{$name} Image.png";
            $iconFileNameLarge = "$IconoutputDirectory/large/Sightseeing Log - SHB0{$number}-Complete.png";

            // copy the input icon to the output filename
            copy($itemIconsmall, $iconFileName);
            copy($itemIconlarge, $iconFileNameLarge);

            // Save some data
            $data = [
                '{patch}' => $Patch,
                '{name}' => $name,
                '{emote}' => $emote,
                '{impression}' => $item['Impression'],
                '{description}' => $item['Description'],
                '{number}' => $number,
                '{time}' => ($item['MinTime'] > 0) ? " {$item['MinTime']}-{$item['MaxTime']}" : '',
            ];

            // format using Gamer Escape formatter and add to data array
            // need to look into using item-specific regex, if required.
            $this->data[] = GeFormatter::format(self::WIKI_FORMAT, $data);
        }

        // (optional) finish progress bar
        $this->io->progressFinish();

        // save
        $this->io->text('Saving data ...');
        $this->save("$CurrentPatchOutput/SightseeingLogs.txt");
    }
}
