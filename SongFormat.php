#!/usr/bin/env php5
<?php

//// Configuration


// Define the tags assigned to each language in a song.
// The curly brackets {} will be added automatically, so 'sp1' 
// will become {sp1}. If you assign an empty string '', no tags
// will be added to the corresponding language lines.
$languageColors=array(
	1=> '',
	2=> 'sp2',
	3=> 'sp3',
	4=> 'sp4',
	5=> 'sp5',
	6=> 'sp6',
	7=> 'sp7',
	8=> 'sp8',
	9=> 'sp9',
); 

// Define the string to look for when extracting CCLI license no 
// information from the text. This is language-dependend. The default
// string used here is the one from the German SongSelect site:
define('CCLI_STRING', 'CCLI-Liednummer');

// Define whether to do the title processing specific to Volksmission
// Freudenstadt. Specifically, this will eliminate any song numbers in 
// the formats 'PJ-nnn' or 'n - ' prefixed to a song title.
define('VMFDS_FORMATTING', TRUE);


// -- nothing to configure below this line
///////////////////////////////////////////////////////////////////////
error_reporting(E_ERROR);
define('VERSION', strftime('%Y%m%d.%H.%M', getlastmod()));
define('CRLF', "\r\n");
//////////////////////////////////////////////////////////////////////

/**
 * Songbeamer .sng song
 * 
 * @author Christoph Fischer <christoph.fischer@volksmission.de>
 */
class SBSong {
	protected $filename;
	public $title;
	protected $songConfig;
	protected $parts;
	protected $divider="\n";

	/**
	 * Create a new instance of the SBSong class
	 * 
	 * The constructor will immediately import the base file, do some title
	 * formatting and CCLI number extraction. It also does utf8 conversion,
	 * always assuming that the source file is not encoded in utf8 
	 * (which is probably not the case on a Windows platform).
	 * 
	 * @param string The .sng file name
	 * @returns void
	 */
	public function __construct ($filename) {
		$this->import($filename);
	}
	
	/**
	 * Import a song from a .sng file
	 * 
	 * @param string .sng file name
	 * @returns void
	 */
	protected function import($filename) {
		$this->filename=$filename;
		$raw = utf8_encode(file_get_contents($filename));
		
		$this->identifyDivider($raw);
		$this->parts=explode('---'.$this->divider, $raw);
		
		$this->importConfig($this->parts[0]);
		unset($this->parts[0]);

		$this->formatTitle();
		$this->getLicense($raw);
	}
	
	/**
	 * Extract license information
	 * 
	 * Some songs simply copied from www.songselect.com have an empty 
	 * CCLI number field, but have a line containing the CCLI number at
	 * the end of the text. This method will look for this line and 
	 * extract its contents into the correct data field.
	 * 
	 * Songs without a CCLI license number will be listed in a file
	 * called unlicensed.txt in the current folder.
	 * 
	 * @param string Raw .sng file contents
	 * @returns void
	 */
	protected function getLicense($raw) {
		if (!$this->songConfig['CCLI']) {
			$lines = explode($this->divider, $raw);
			foreach ($lines as $key => $line) {
				if (substr($line, 0, strlen(CCLI_STRING))==CCLI_STRING) {
					$this->songConfig['CCLI'] = str_replace(CCLI_STRING.' ', '', $line);
				}
			}
		}
		// write a list of all songs without license no.
		if (!$this->songConfig['CCLI']) {
			$fp = fopen('unlicensed.txt', 'a');
			fwrite($fp, $this->filename.CRLF);
			fclose($fp);
		}
	}
	
	/**
	 * Remove PJ-nnn and initial song numbers from the title
	 * 
	 * @param string Song title
	 * @returns string Song title without the number parts
	 */
	protected function removeTitleParts($t) {
		if (VMFDS_FORMATTING) {
			// get rid of PJ-...
			if (substr($t, 0, 2)=='PJ') $t = substr($t, 9);
			// get rid of initial song numbers
			$tmp = explode(' - ', $t);
			if (count($tmp)) {
				if (is_numeric($tmp[0])) $t = str_replace($tmp[0].' - ', '', $t);
			}
		}
		return $t;
	}
	
	/**
	 * Format the song title
	 * 
	 * If no song title is present, the file name is used as title
	 * 
	 * @params void
	 * @returns void
	 */
	protected function formatTitle() {
		$t = $this->removeTitleParts($this->songConfig['Title']);

		if (!(trim($t))) {
			// no title? get it from filename
			$t = $this->filename;
			$t = $this->removeTitleParts(str_replace('.sng', '', str_replace('.RR', '', $t)));
		}
		
		// compromise needed? Rather a strange title than an empty one
		if (!trim($t)) $t = $this->songConfig['Title'];
		
		$this->songConfig['Title'] = $t;
		$this->title = $t;
	}

	/**
	 * Identify the line break character
	 * 
	 * This function checks whether \r\n (Windows standard) or \n
	 * (Unix standard) was used as the line break control character
	 * in the source file.
	 * 
	 * @param string Raw .sng file text
	 * @returns void
	 */
	protected function identifyDivider($raw) {
		$lines=explode("\n", $raw);
		if (substr($lines[0], -1) == "\r") $this->divider="\r\n";
	}
	
	/**
	 * Import configuration from .sng file
	 * 
	 * This method imports the configuration from the .sng file's
	 * header into an array.
	 * 
	 * @param string Raw text of the .sng file header
	 * @returns void
	 */
	protected function importConfig($rawPart) {
		$lines = explode($this->divider, $rawPart);
		foreach ($lines as $line) {
			if (trim($line)) {
				$line = substr($line, 1);
				$cfg=explode('=', $line);
				$this->songConfig[trim($cfg[0])]=trim($cfg[1]);
			}
		}
	}
		
		
	/**
	 * Tag the different languages in a song
	 * 
	 * @param array languageColors array assigning a tag to each language number
	 * @returns void
	 */
	public function processLanguages($colors) {
		if ($this->songConfig['LangCount']>1) {
			foreach ($this->parts as $pk => $part) {
				$lines = explode($this->divider, $part);
				$idx = 1;
				foreach ($lines as $key => $line) {
					if (!$this->skipLine($line)) {
						$manual=false;
						// check for manually set index
						if (substr($line, 0, 2)=='##') {
							$idx = substr($line, 2, 1);
							$line = substr($line, 4);
							$manual = true;
						}
						// tag color
						if ($idx>1) {
							if ($colors[$idx]) {
								$lines[$key] = '{'.$colors[$idx].'}'
											  .$line
											  .'{/'.$colors[$idx].'}';
							}
						}
						if (!$manual)
							if ($idx==$this->songConfig['LangCount']) $idx=1; else $idx++;
					}
				}
				$this->parts[$pk]=join($this->divider, $lines);
			}
		}
	}
	
	/**
	 * Checks whether a line should be skipped
	 * 
	 * Lines containing song-part keywords like verse, refrain, ...
	 * should be skipped and not counted as a new language line
	 * 
	 * @param string Line from a song
	 * @returns bool True when the line should be skipped
	 */
	protected function skipLine($line) {
		$line = explode(' ', $line);
		$keyWord=$line[0];
		$f=in_array($keyWord, array('unbekannt', 'unbenannt', 'unknown', 'intro', 'vers', 'verse', 'strophe',
									'refrain', 'chorus', 'pre-bridge', 'bridge', 'ending', 'pre-refrain', 'pre-chorus',
									'pre-coda', 'zwischenspiel', 'interlude', 'coda', 'teil', 'part', '$$m=', '#h',
									));
		return $f;
	}
	
	/**
	 * Write the song back to a .sng file
	 * 
	 * @param void
	 * @returns void
	 */
	public function write() {
		// create config block
		foreach ($this->songConfig as $key=> $value) {
			$cfg .='#'.$key.'='.$value.$this->divider;
		}
		$raw = join($this->divider.'---'.$this->divider, $this->parts);
		
		$fp=fopen($this->filename, 'w');
		fwrite($fp, $cfg.'---'.$this->divider);
		fwrite($fp, $raw);
		fclose($fp);
	}
}


///////////////////////////////////////////////////////////////////////
echo 'SongFormatter v'.VERSION.CRLF;
echo '(c) Volksmission Freudenstadt'.CRLF;
echo CRLF;

if ($handle=opendir('.')) {
	while (false !== ($file = readdir($handle))) {
		if (pathinfo($file, PATHINFO_EXTENSION)=='sng') {
			$song=new SBSong($file);
			echo 'Converting song "'.$song->title.'"... ';
			$song->processLanguages($languageColors);
			$song->write();
			echo 'OK'.CRLF;
		}
	}
	closedir($handle);
}
echo 'Done.'.CRLF;
