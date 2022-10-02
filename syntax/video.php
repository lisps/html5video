<?php
/**
 * DokuWiki Plugin html5video (Syntax Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html 
 * @author lisps
 * Parts borrowed from the videogg plugin written by Ludovic Kiefer and from the html5video plugin written by 
 * Jason van Gumster (Fweeb) <jason@monsterjavaguns.com> which is based on Christophe Benz' Dailymotion plugin, which, in turn,
 * is based on Ikuo Obataya's Youtube plugin.
 *
 * Currently only supports h264 videos
 */

class syntax_plugin_html5video_video extends DokuWiki_Syntax_Plugin {

    public function getType() {
        return 'substition';
    }

    public function getPType() {
        return 'normal';
    }

    public function getSort() {
        return 159;
    }
 
    public function connectTo($mode) { 
		// {file}.mp4?{width}x{height}?{file}|{alternatetext}
		$this->Lexer->addSpecialPattern('\{\{[^\}]+\.mp4[^\}]*\}\}',$mode,'plugin_html5video_video');
    }

    /**
     * mostly copied from handler.php -> Doku_Handler_Parse_Media()
     */
    public function handle($match, $state, $pos, Doku_Handler $handler){
        
        
        // Strip the opening and closing markup
        $link = preg_replace(array('/^\{\{/','/\}\}$/u'),'',$match);

        // Split title from URL
        $link = explode('|',$link,2);

        // Check alignment
        $ralign = (bool)preg_match('/^ /',$link[0]);
        $lalign = (bool)preg_match('/ $/',$link[0]);

        // Logic = what's that ;)...
        if ( $lalign & $ralign ) {
            $align = 'center';
        } else if ( $ralign ) {
            $align = 'right';
        } else if ( $lalign ) {
            $align = 'left';
        } else {
            $align = NULL;
        }

        // The title...
        if ( !isset($link[1]) ) {
            $link[1] = NULL;
        }

        //remove aligning spaces
        $link[0] = trim($link[0]);

        //split into src and parameters (using the very last questionmark)
        $pos = strrpos($link[0], '?');
        if($pos !== false){
            $src   = substr($link[0],0,$pos);
            $param = substr($link[0],$pos+1);
        }else{
            $src   = $link[0];
            $param = '';
        }

        //parse width and height
        if(preg_match('#(\d+)(x(\d+))?#i',$param,$size)){
            ($size[1]) ? $w = $size[1] : $w = NULL;
            ($size[3]) ? $h = $size[3] : $h = NULL;
        } else {
            $w = 640;
            $h = 360;
        }

        if(preg_match('/linkonly/i',$param)){
            $linking = 'linkonly';
        } else {
            $linking = '';
        }
        
        $params = explode('&',$param);
        $poster = $this->getConf('GlobalVideoPreviewPicture');
        foreach($params as $p){
            if ($this->media_exists($p))
                $poster = cleanID($p);
        }
        
        /*
        dbg(array(
            'link'=>$link,
            'src'=>$src,
            'param'=>$param,
            'size'=>$size,
            'w'=>$w,
            'h'=>$h,
            'linking'=>$linking,
            'align'=>$align,
            'poster'=>$poster,
        ));
        */
        
        if ( preg_match('#^(https?|ftp)#i',$src) ) {
            $type = 'externalmedia';
        } else {
            $type = 'internalmedia';
        }
        return array(
            $src,
            $align,
            $w,
            $h,
            $linking,
            $poster,
            $type,
            $link[1]
            
        );
    }
    
    protected function media_exists($id) {
        return @file_exists(mediaFN($id));
    }

    public function render($mode, Doku_Renderer $renderer, $data) {
	    global $ID;
        // initalisize video class id 
        static $counter = 1;  
        
        if($mode != 'xhtml') {
			return false;
		}
		
        list($src,
            $align,
            $w,
            $h,
            $linking,
            $poster,
            $type,
            $alt) = $data;
        
        $exists = false;
		resolve_mediaid(getNS($ID), $src, $exists);
            
		if($type == 'internalmedia' && !$exists) {
            $renderer->internalmedia($src,$alt,$align,$w,$h);
            return true;
        }
        
        if($linking == 'linkonly') {
            //$alt = $alt?$alt:hsc($src);
            // Check whether this is a local or remote image
            if ( $type == 'externalmedia' ) {
                $renderer->externalmedia($src,$alt,$align,$w,$h,NULL,$linking);
            } else {
                $renderer->internalmedia($src,$alt,$align,$w,$h,NULL,$linking);
            }
            return true;
        }
		
        

		// preprocess content to display on screen
		$obj = '<video id="'.hsc($this->getConf('videoPlayerIDText')).'' . $counter . '" class="video-js vjs-default-skin media'.$align.'" '. 
            ($w ? ('width="'  .$w. '" ') : ''). 
            ($h ? ('height="' .$h. '" ') : ''). 
            ' controls preload="'.hsc($this->getConf('VideoPreload')).
            '" '.($poster? 'poster="'.hsc(''.ml($poster)).'" ':'').
            ' data-setup=\'{"techOrder": ["'.hsc($this->getConf('preferedVideoTechnologie')).'", "'.hsc($this->getConf('fallBackVideoTechnologie')).
            '"]}\'>';

        $obj .= '<source src="' . ml($src) . '" type="'.hsc($this->getConf('html5VideoType')).'" /></video>';
		
		// preprocess content to print
		if($this->getConf('showThumbOnPrint') && $poster != "") {
			// Print Picture if specified 
			$obj .= '<div class="vjs-alternatetext"><img src="' . ml($poster) . '" alt="' . hsc($alt) . '"  '.
		($w ? ('width="'  .$w. 'px" ') : '').
		($h ? ('height="' .$h. 'px" ') : '').
                '></div>'; 
		} else if($alt != "") { 
			// Print alternate text if specified
			$obj .= '<div class="vjs-alternatetext">' . hsc($alt) . '</div>'; 		
		} else  if($this->getConf('showStandardTextOnPrint')) {
			// Print standard alternate text
			$obj .= '<div class="vjs-alternatetext">' . hsc($this->getConf('standardAlternateTextPrint')) . '</div>'; 		 
		}

		// increment video class id on current page
		$counter++;	
		
		// set render output
        $renderer->doc .= $obj;
        return true;
    }
    private function _getAlts($filename) {
        return false;
    }
}
