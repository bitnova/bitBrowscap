<?php
	/**
	 *	bitBrowscap - fast lightweight library for parsing php_browscap.ini file and determining browser capabilities
	 * 	Copyright (C) 2017 bitnova
	 * 	
	 * 	This library is free software; you can redistribute it and/or
	 * 	modify it under the terms of the GNU Lesser General Public
	 * 	License as published by the Free Software Foundation; either
	 * 	version 2.1 of the License, or (at your option) any later version.
	 * 	
	 * 	This library is distributed in the hope that it will be useful,
	 * 	but WITHOUT ANY WARRANTY; without even the implied warranty of
	 * 	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
	 * 	Lesser General Public License for more details.
	 *	
 	 *	@name:		bitBrowscap
	 *	@author:	Dan-Alexandru Avasi, bitnova, <dan@bitnova.ro>
	 *	@license:	LGPL-2.1 <http://www.gnu.org/licenses/lgpl-2.1.txt>
	 */
	
	class TbitBrowscap
	{
		static private $Finstance = null;

		function instance()
		{
			if (self::$Finstance != null) return self::$Finstance;

			self::$Finstance = new TbitBrowscap();
			return self::$Finstance;
		}
		
		var $cacheDir = 'cache';
		var $iniFilename = 'php_browscap.ini';
		var $maxNodesPerCacheFile = 1250;

		function __construct()
		{
		}
		
		protected function build_tree(&$agents_tree, $agent)
		{
			//  parse each character from $agent
			$agent_len = strlen($agent);
			$j = 0;
			$key = '';
			$key_len = 0;
			$cur_agent = &$agents_tree;
			$parent;// = null;
				
			$i = -1;
			while ($i < $agent_len - 1)
			{
				$i++;
				$char = $agent[$i];
			
				if ($j < $key_len && $key[$j] == $char) $j++;
				else
				{
					//  the key no longer matches we have to split, search children or add new entries
					if ($j >= $key_len)
					{
						$b = false;
						$child_key = '';
						foreach($cur_agent as $child_key => &$val)
							if (((string) $child_key)[0] == $char) { $b = true; break; }
			
						if (!$b)
						{
							$cur_agent[substr($agent, $i)] = array();
							$i = $agent_len;
						}
						else
						{
							$key = (string) $child_key; //$keys[$j];
							$key_len = strlen($key);
							$parent = &$cur_agent;
							$cur_agent = &$cur_agent[$key];//&$cur_agent[$keys[$j]];
							$j = 0;
							$i--;
						}
					}
					else
					{
						//  split current key at $j position
						$prefix = substr($key, 0, $j);
						$sufix = substr($key, $j);
						$parent[$prefix] = array();
						$parent[$prefix][$sufix] = $cur_agent;
						$parent[$prefix][substr($agent, $i)] = array();
						unset($parent[$key]);
						//$parent = &$cur_agent;
						$i = $agent_len;
						$j = $key_len;
					}
				}
			}
		}
		
		protected function fill_tree(&$agents_tree, $agent, &$values)
		{
			$cur_agent = &$agents_tree;
			$prefix = '';
			$sufix = $agent;
			$sufix_len = strlen($sufix);
				
			while ($sufix_len > 0 && count($cur_agent) > 0)
			{
				$b = false;
				foreach ($cur_agent as $key => &$kids)
				{
					$key_len = strlen($key);
					$idx = strpos($sufix, (string) $key);
					if ($key_len <= $sufix_len && $idx === 0)
					{
						$prefix.= $key;
						$sufix = substr($sufix, $key_len);
						$sufix_len = strlen($sufix);
						$cur_agent = &$kids;
						$b = true;
						break;
					}
				}
				if (!$b) break;
			}
				
			if ($b) //$cur_agent[$key] = &$ini_section;
				foreach ($values as $key => $value) $cur_agent[$key] = $value;
		}
		

		protected function generate_md5name($name)
		{
			srand();
			$s = $name.'::'.gettimeofday(true).'::'.rand(0, pow(2, 32));
			return md5($s);
		}
		
		protected function save_tree(&$agents_tree)
		{
			$k = 0;
			if (count($agents_tree) == 1) return 1;
				
			foreach ($agents_tree as $agent => &$values)
			{
				if (is_array($values)) $n = $this->save_tree($values);
				else $n = 1;
			
				if ($n >= $this->maxNodesPerCacheFile)
				{
					$name = 'browscap_'.$this->generate_md5name($agent);
					file_put_contents($this->cacheDir.DIRECTORY_SEPARATOR.$name, serialize($values));
					//file_put_contents($this->cacheDir.DIRECTORY_SEPARATOR.$name, json_encode($values, JSON_PRETTY_PRINT));
					unset($values);
					$agents_tree[$agent] = 'link::'.$name;
				}
				else $k += $n;
			}
				
			return $k;
		}

		function buildCache()
		{
			if (!is_dir($this->cacheDir)) mkdir($this->cacheDir);
			
			$agents = array();
			$wildcards = array();
			$ini_sections = parse_ini_file($this->iniFilename, true, INI_SCANNER_RAW);
			
			//  build the actual trees
			ksort($ini_sections);
			foreach($ini_sections as $agent => &$ini_section)
			{
				if (strpos($agent, '*') === false) $this->build_tree($agents, $agent);
				else $this->build_tree($wildcards, $agent);
			}
			
			//  add the browser info
			set_time_limit(60);
			foreach ($ini_sections as $agent => &$ini_section)
			{
				if (strpos($agent, '*') === false) $this->fill_tree($agents, $agent, $ini_section);
				else $this->fill_tree($wildcards, $agent, $ini_section);				
			}
			
			//  parse the tree and save partial trees to files
			set_time_limit(30);
			$this->save_tree($agents);
			file_put_contents($this->cacheDir.DIRECTORY_SEPARATOR.'browscap', serialize($agents));
			//file_put_contents($this->cacheDir.DIRECTORY_SEPARATOR.'browscap', json_encode($agents, JSON_PRETTY_PRINT));
			//unset($agents);
			
			$this->save_tree($wildcards);
			file_put_contents($this->cacheDir.DIRECTORY_SEPARATOR.'browscap_wildcards', serialize($wildcards));
			//file_put_contents($this->cacheDir.DIRECTORY_SEPARATOR.'browscap_wildcards', json_encode($wildcards, JSON_PRETTY_PRINT));
		}
		
		protected $agent = '';
		
		protected function echo_indent($indent, $text)
		{
			echo '<span>'; for ($i = 0; $i < 2 * $indent; $i++) echo '&nbsp;';
			echo $text;
			echo '</span></br>';
		}
		
		protected function open_link($link)
		{
			$filename = substr($link, 6);
			$path = $this->cacheDir.DIRECTORY_SEPARATOR.$filename;
			if (file_exists($path))
			{
				$values = unserialize(file_get_contents($path));
				//$values = json_decode(file_get_contents($path), true);
				//$this->echo_indent(0, 'opened ['.$path.']');
				
				return $values;
			}
			
			return array();
		}
		
		protected function parse_tree(&$agents_tree, $agent, $k, $mask)
		{
			//echo '<span>'; for ($i = 0; $i < 2 * $k; $i++) echo '&nbsp;'; echo 'match ['.$agent.']'; echo '</span></br>';
			
			//  take the next char from the user_agent
			$len = strlen($agent);
			$char = '';
			if ($len > 0) $char = strtolower($agent[0]);
			
			$remainder = substr($agent, 1);
			$next = array(); //  the next search level
			$joker_key = null;
			$joker_values = null;
			$w_key = null;
			$w_values = null;
			foreach ($agents_tree as $key => &$values)
			{
				$char_k = strtolower(((string)$key)[0]);
				$vkey = substr($key, 1);
				
				//  delay checking the following two branches in order to eliminate possible perfect matches first
				if ($char_k == '?')
				{
					$joker_key = $key;
					$joker_values = $values;
				}
				
				if ($char_k == '*')
				{
					$w_key = $key;
					$w_values = $values;
				}
								
				if ($char_k == $char)
				{
					//$this->echo_indent($k, 'check ['.$key.']');
					$vkey = substr($key, 1);
					if (strlen($vkey) == 0) 
					{
						//  expand the node
						//  if we find a link here, we load the file
						if (is_string($values) && strpos($values, 'link::') !== false) $values = $this->open_link($values);					
						foreach ($values as $vkey => &$vval) $next[$vkey] = $vval;
					}
					else $next[$vkey] = $values;
				}
				
				if ($len == 0 && is_string($values)) $next[$key] = $values;
			}
			
			//  check if we reached the end of the agent string and we have key => value pairs in our result
			if ($len == 0 && count($next) > 0)
			{
				$next['mask'] = $mask.$char;
				return $next;
			}
				
			//  try to pass the next tree level to the parser again
			$res1 = null;
			$mask1 = '';
			if ($len > 0 && count($next) > 0) 
			{
				$res1 = $this->parse_tree($next, $remainder, $k + 1, $mask.$char);				
				if (is_array($res1) && count($res1) > 0) 
				{
					//  agent string was matched in the subsequent searches
					if (isset($res1['mask'])) $mask1 = $res1['mask'];
					//return $res1; 
				}
			}
			
			//  check if we have char joker and if this provides some results
			$res2 = null;
			$mask2 = '';
			if (!empty($joker_key))
			{
				//  slide the remainder by one char and recheck
				$remainder = substr($remainder, 1);
				$res2 = $this->parse_tree($joker_values, $remainder, $k + 1, $mask.'?');
				if (is_array($res2) && count($res2) > 0) 
				{
					//  agent string was matched in the subsequent searches
					if (isset($res2['mask'])) $mask2 = $res2['mask'];
					//return $res2; 
				}
			}
			
			//  we have a wildcard to check, attempt to select best nodes from this branch and deep search
			//  only on meaningful sub-branches
			$res3 = null;
			$mask3 = '';
			if (!empty($w_key))
			{
				$mask .= '*';
				$vkey = substr($w_key, 1);
				if (strlen($vkey) > 0)
				{
					//  check if there is a second wildcard and validate the
					//  part in between against the agent string
					$head = $vkey;
					$tail = '';
					$pos = strpos($vkey, '*');
					if ($pos !== false) 
					{
						$head = substr($vkey, 0, $pos);
						$tail = substr($vkey, $pos);
					}
						
					$pos = stripos($agent, $head);
					if ($pos !== false)
					{
						//  continue searching from the part in between on this branch
						$ag = substr($agent, $pos + strlen($head));
						if (!empty($tail))
						{
							$w = array($tail => $w_values);
							$res3 = $this->parse_tree($w, $ag, $k + 1, $mask.$head);
						}
						else
						{
							if (is_string($w_values) && strpos($w_values, 'link::') !== false) $w_values = $this->open_link($w_values);								
							$res3 = $this->parse_tree($w_values, $ag, $k + 1, $mask.$head);
						}
						
						if (is_array($res3) && count($res3) > 0) 
						{
							if (isset($res3['mask'])) $mask3 = $res3['mask'];
							if (strlen($mask3) > strlen($mask1) && strlen($mask3) > strlen($mask2)) return $res3;
						}
					}
				}
				else
				{
					//  load values if necessary
					if (is_string($w_values) && strpos($w_values, 'link::') !== false) $w_values = $this->open_link($w_values);
					
					$arr = array();
					foreach ($w_values as $vkey => &$vval)
					{
						if (is_string($vval) && strpos($vval, 'link::') === false) $arr[$vkey] = $vval;
						else
						{
							$head = $vkey;
							$tail = '';
							$pos = strpos($vkey, '*');
							if ($pos !== false)
							{
								$head = substr($vkey, 0, $pos);
								$tail = substr($vkey, $pos);
							}
								
							$pos = stripos($agent, $head);
							if ($pos !== false)
							{
								$ag = substr($agent, $pos + strlen($head));
								if (!empty($tail))
								{
									$w = array($tail => $vval);
									$res3 = $this->parse_tree($w, $ag, $k + 1, $mask.$head);
								}
								else 
								{
									if (is_string($vval) && strpos($vval, 'link::') !== false) $vval = $this->open_link($vval);
									$res3 = $this->parse_tree($vval, $ag, $k + 1, $mask.$head);
								}
									
								if (is_array($res3) && count($res3) > 0) 
								{
									if (isset($res3['mask'])) $mask3 = $res3['mask'];
									if (strlen($mask3) > strlen($mask1) && strlen($mask3) > strlen($mask2)) return $res3;
								}
							}							
						}
					}
					
					if (count($arr) > 0) 
					{
						$res3 = $arr;
						$res3['mask'] = $mask;
						$mask3 = $mask;
						//return $res3;
					}
				}
			}
			
			if (is_array($res1) && count($res1) > 0 && strlen($mask1) > strlen($mask2) && strlen($mask1) > strlen($mask3)) return $res1;
			if (is_array($res2) && count($res2) > 0 && strlen($mask2) > strlen($mask1) && strlen($mask2) > strlen($mask3)) return $res2;
			if (is_array($res3) && count($res3) > 0)
				if (strlen($mask3) > strlen($mask1))
					if (strlen($mask3) > strlen($mask2)) return $res3;
		}
		
		function getBrowser($user_agent = null, $return_array = false)
		{
			//  load tree data
			$agents = null;
			$wildcards = null;

			$path_agents = $this->cacheDir.DIRECTORY_SEPARATOR.'browscap';
			$path_wildcards = $this->cacheDir.DIRECTORY_SEPARATOR.'browscap_wildcards'; 
			
			if (!file_exists($path_agents) || !file_exists($path_wildcards)) $this->buildCache();
			
			$agents = unserialize(file_get_contents($path_agents));
			$wildcards = unserialize(file_get_contents($path_wildcards));
			//$agents = json_decode(file_get_contents($path_agents), true);
			//$wildcards = json_decode(file_get_contents($path_wildcards), true);
				
			$this->agent = $user_agent;
			if (!isset($this->agent)) $this->agent = $_SERVER['HTTP_USER_AGENT'];

			//  parse the tree		
			$result = array();
			$browser = $this->parse_tree($wildcards, $this->agent, 0, '');
			while (isset($browser) && is_array($browser))
			{
				$parent = '';
				
				foreach ($browser as $key => $value)
				{
					if (is_array($value) || is_object($value)) continue;
					if ($key == 'Parent') $parent = $value;
					if (!key_exists($key, $result)) $result[$key] = $value;
				}
					
				unset($browser);
				if (!empty($parent)) $browser = $this->parse_tree($agents, $parent, 0, '');
			}
			
			return $return_array ? $result : (object) $result;				
		}
		
	}

?>
