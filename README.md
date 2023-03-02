# Sons Of The Forest Inventory Editor

**Warning: This project is a spaghetti code, made for personal usage but shared to the public!**

This tool lets you edit your inventory inside your save file.  
The game is currently in early access and there are multiple bugs that make you lose stuff, this tool gives you easy way of restoring it. 

### Backup your saves before editing.

<a href="https://i.imgur.com/ot0ABFN.png"><img src="https://i.imgur.com/ot0ABFN.png" height="300"></a>

## Installation/Usage

Download release zip from [Releases](https://github.com/jacklul/Sons-Of-The-Forest-Inventory-Editor/releases) tab and extract it to directory of your choice, run the tool using `phpdesktop-chrome.exe` executable.

Script should automatically find your saves if they are in the default path (`C:\Users\%USERNAME%\AppData\LocalLow\Endnight\SonsOfTheForest\Saves`), otherwise you will be asked to paste the path to this directory manually.

If everything went well you will see a select dropdown with all your saves sorted by the last modified time.

To modify count of specified item you have to either enter `+1` to add or `-1` to remove one unit of that item *(you can specify any number to add/remove more)* then click **MODIFY**. You can quickly fill item to max capacity by using **FILL** button.

You cannot modify equipped items, make sure you unequip everything before saving the game.

You can exceed capacity limits by setting `allowExceedMaxCapacity` to `true` in `data/config.json`, be cautious when doing so.
