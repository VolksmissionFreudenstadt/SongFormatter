#!/usr/bin/env php5
<?php

//// Configuration
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

///////////////////////////////////////////////////////////////////////
error_reporting(E_ERROR);
define('VERSION', strftime('%Y%m%d.%H.%M', getlastmod()));
define('CRLF', "\r\n");
///////////////////////////////////////////////////////////////////////

class SBSong {
	protected $filename;
	public $title;
	protected $songConfig;
	protected $parts;
	protected $divider="\n";

	public function __construct ($filename) {
		$this->import($filename);
	}
	
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
	
	protected function getLicense($raw) {
		if (!$this->songConfig['CCLI']) {
			$lines = explode($this->divider, $raw);
			foreach ($lines as $key => $line) {
				if (substr($line, 0, 15)=='CCLI-Liednummer') {
					$this->songConfig['CCLI'] = str_replace('CCLI-Liednummer ', '', $line);
				}
			}
		}
		if (!$this->songConfig['CCLI']) {
			$fp = fopen('unlicensed.txt', 'a');
			fwrite($fp, $this->filename.CRLF);
			fclose($fp);
		}
	}
	
	protected function removeTitleParts($t) {
		// get rid of PJ-...
		if (substr($t, 0, 2)=='PJ') $t = substr($t, 9);
		// get rid of initial song numbers
		$tmp = explode(' - ', $t);
		if (count($tmp)) {
			if (is_numeric($tmp[0])) $t = str_replace($tmp[0].' - ', '', $t);
		}
		return $t;
	}
	
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

	protected function identifyDivider($raw) {
		$lines=explode("\n", $raw);
		if (substr($lines[0], -1) == "\r") $this->divider="\r\n";
	}
	
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
	
	protected function skipLine($line) {
		$line = explode(' ', $line);
		$keyWord=$line[0];
		$f=in_array($keyWord, array('unbekannt', 'unbenannt', 'unknown', 'intro', 'vers', 'verse', 'strophe',
									'refrain', 'chorus', 'pre-bridge', 'bridge', 'ending', 'pre-refrain', 'pre-chorus',
									'pre-coda', 'zwischenspiel', 'interlude', 'coda', 'teil', 'part', '$$m=', '#h',
									));
		return $f;
	}
	
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
