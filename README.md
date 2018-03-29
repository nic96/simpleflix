# SimpleFlix
A project I made for myself over a weekend to host my movies and tv shows on my Raspberry Pi.

## Setup
1. Setup a webserver such as nginx or apache. Make sure the server is setup for php and pdo_sqlite.
2. Setup the database with the following commands:
```
cat db.sql | sqlite3 db.sqlite
```
3. Get an API key from [ombdbapi](http://www.omdbapi.com/apikey.aspx).
4. Edit `scan.php` and change `$omdb_apikey = '';` to `$omdb_apikey = 'youromdbapikeyhere';`
5. Change any other variables in scan.php you might need then you're good to go.
6. Place your Movies in `Media/Movies/` and your TV Shows in `Media/TV Shows/`. Can have any number of subdirectories.
7. After all that is setup open up your browser and go to the address where you're hosting SimpleFlix. Run the first scan using the
Scan button in the left Panel.

## Issues
### You ran the scan, but your Movies and TV Shows don't show up.
This may be due to the naming of your Video files. SimpleFlix tries to use the video file names as titles to get information about
the videos from omdbapi.

SimpleFlix won't show videos that it hasn't been able to retrieve information about. This could be fixed, but I currently don't need
it. Until I need it it won't be implemented unless you do it.

## TODO
1. Clean up the code and improve the css. Maybe make the code object oriented.
