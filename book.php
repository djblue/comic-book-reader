<?php if (!defined('ACTIVE')) exit('No direct script access allowed');

// class to represent a book
//  - scans comic book directory.
//  - normalizes rar/zip interface.
//  - maintains the database of current books.

class Book {

    // class reference to database
    public static $db;
    // class reference of config object
    public static $config;

    public $md5;

    // Instance Variable
    private $path;
    private $ext;
    private $pages;
    private $archive;


    // Initialize a book object using an id.  if id is not number, assume
    // path to file.
    public function __construct($id) {
    
        if (is_numeric($id)) {

            // Search the database based on the supplied $id.
            $query = self::$db->prepare('SELECT `path`,`ext`,`pages`,`md5` FROM books
                WHERE `id`=:id');

            $query->bindValue(':id', $id);

            $result = $query->execute()->fetchArray(SQLITE3_ASSOC);
             
            if ($result == false) {
                throw new Exception("Unable to find book with id: ".$id);
            }

            // The entry was found and the object is properly initialized.
            $this->path  = $result['path'];
            $this->ext   = $result['ext'];
            $this->pages = $result['pages'];
            $this->md5   = $result['md5'];

        } else {

            $this->path = $id;
            $this->ext  = pathinfo($id)['extension'];

        }


        // try to initialize an archive reference
        $this->archive = false;

        if ($this->ext == 'cbr') {
            $this->archive = RarArchive::open($this->path); 
        } else if ($this->ext == 'cbz') {
            $this->archive = new ziparchive();
            $this->archive->open($this->path);
        }
    }

    // clean up, clean up, everybody clean up.
    public function __destruct () { 
        if ($this->archive != false)
            $this->archive->close(); 
    }

    // return array of valid jpg entries in archive
    private function get_entries () {

        $entries = []; // the would be list of jpg entries

        // create array of entries
        if ($this->ext == 'cbr') {
            $entries = array_map(function ($entry) {
                return $entry->getName();
            }, $this->archive->getEntries());
        } else if ($this->ext == 'cbz') {
            for ($i = 0; $i < $this->archive->numFiles; $i++) {
                array_push($entries, $this->archive->getNameIndex($i));
            }
        }

        // only consider jpgs and pngs as valid entries
        $entries = array_filter($entries, function ($title) {
            if (preg_match('/(JPEG|jpeg|JPG|jpg)$/', $title)) {
                return true;
            } else if (preg_match('/(PNG|png)$/', $title)) {
                return true;
            }

            return false;
        });

        sort($entries);      // sort entires

        // try to remove annoying promos
        $sample = $entries[0];
        $entries = array_filter($entries, function ($entry) use ($sample) {
            return levenshtein($sample, $entry) < 6;
        });

        sort($entries);  // one last sort to fix everything back up

        return $entries; // and now we are done.
    }

    // Get the page entries of the current book.  
    public function get_page_stream($page) {

        $this->get_entries();

        $stream = false; // Initialize the stream to false.

        // make sure the page requested is in range
        if ($page >= 0 && $page < $this->pages) {

            if ($this->ext == 'cbr') {

                $stream =  $this->archive->getEntry($this->get_entries()[$page])->getStream();

            } else if ($this->ext == 'cbz') {

                $stream = $this->archive->getStream($this->get_entries()[$page]);
            }  

        }
        
        return $stream;
    }

    // Get the current book list. 
    public static function list_all_books() {

        $array = [];

        $result = self::$db->query('SELECT *  FROM books');

        while($row = $result->fetchArray(SQLITE3_ASSOC)) {

            $array[] = $row;
        }
        
        return $array;
    }

    // Start scanning from the root directory in the config file.
    public static function scan () {
        self::tree(self::$config['base_dir'], 0);
    }
    
    // Scan book directory to populate the database.
    private static function tree($path, $parent_id) {

        $ignore = ['cgi-bin', '.', '..'];

        // Open the directory to the handle $dh
        $dir_handle = @opendir($path) or die("Unable to open $path");

        // Iteration through current directory.
        while ($file = readdir($dir_handle))
        {
            if (!in_array($file, $ignore))
            {  
                if (is_dir("$path/$file"))
                { 
                    // generate digest based on path, which for now will uniquely
                    // identify the comic book archive
                    $md5 = md5("$path/$file");

                    $query = self::$db->prepare('SELECT `id` FROM folders WHERE `md5`=:md5');
                    $query->bindValue(':md5', $md5);
                    $result = $query->execute()->fetchArray(SQLITE3_ASSOC);

                    if ($result == false) {

                        //try {

                            $q = self::$db->prepare("INSERT INTO folders
                            (`parent`,`md5`,`label`) VALUES (
                                :parent, :md5, :label)");

                            $q->bindValue(":parent", $parent_id);
                            $q->bindValue(":md5",    $md5);
                            $q->bindValue(":label",  $file);

                            $q->execute();

                        //} catch (Exception $e) {}

                        // Recursively add children.
                        self::tree("$path/$file", self::$db->lastInsertRowid());
                    }

                    self::tree("$path/$file", $result['id']);
                    
                }
                else
                {
                    // generate digest based on path, which for now will uniquely
                    // identify the comic book archive
                    $md5 = md5("$path/$file");

                    $query = self::$db->prepare('SELECT `id` FROM books WHERE `md5`=:md5');
                    $query->bindValue(':md5', $md5);
                    $result = $query->execute()->fetchArray(SQLITE3_ASSOC);
                     
                    // book not presently in the database
                    if ($result == false) {

                        try {
                            // Preparing an SQL query.
                            $q = self::$db->prepare("INSERT INTO books 
                                (`folder`,`md5`,`label`,`path`,`ext`,`pages`) VALUES (
                                :folder, :md5, :label, :path, :ext, :pages)");

                            $q->bindValue(":folder",    $parent_id);
                            $q->bindValue(":md5",       $md5);
                            $q->bindValue(":label",     pathinfo($file)['filename']);
                            $q->bindValue(":path",      "$path/$file");
                            $q->bindValue(":ext",       pathinfo($file)['extension']);

                            $book = new Book("$path/$file");
                            $q->bindValue(":pages", count($book->get_entries()));

                            $q->execute();

                        } catch (Exception $e) {
                            /*echo 'Exception '. $e->getMessage() .  * '\n';*/
                        }
                    }
                }
            }
        }

        closedir($dir_handle);
    }

}

Book::$config = $config;

Book::$db = new SQLite3("/comics/cbviewer.db");

// define the schema of the  table.
Book::$db->query("
    CREATE TABLE IF NOT EXISTS folders (
        id          INTEGER NOT NULL  PRIMARY KEY,
        parent      INTEGER NOT NULL,
        md5         TEXT    NOT NULL,
        label       TEXT    NOT NULL,
        FOREIGN KEY(parent) REFERENCES folders(id)
    )
");

// define the schema of the books table.
Book::$db->query("
    CREATE TABLE IF NOT EXISTS books (
        id          INTEGER NOT NULL  PRIMARY KEY,
        folder      INTEGER NOT NULL,
        md5         TEXT    NOT NULL,
        label       TEXT    NOT NULL,
        path        TEXT    NOT NULL,
        ext         TEXT    NOT NULL,
        pages       INTEGER NOT NULL,
        current     INTEGER DEFAULT 0,
        FOREIGN KEY(folder) REFERENCES folders(id)
    )
");
