<?php
class QQMhtSpliter2 {
	const HTML_END_TAG = '</table></body></html>';
	private $per; //单位M，小于$per将不能分割

	private $dat_arr = []; //不存在的图片 保存在这里
	private $base_dir; //转换完成-目录
	private $convert_name = ''; //转换完成-文件名
	private $image_type = []; //每张图片的格式

	public function __construct($per = 40) {
		$this->per = $per;
	}

	public function convert() {
		$s = microtime(1);
		$m = memory_get_usage();
		$chat_file = $this->read_dir();
		$file_name_prefix = str_replace('.mht', '', $chat_file) . '_';
		$handle = fopen($chat_file, 'rb');
		for($i = 0; $i < 12; $i++) { 
			$row = fgets($handle);
			if(strpos($row, 'boundary="----=_NextPart') !== FALSE) {
				$sep = str_ireplace(['boundary=', '"'], '', trim($row));
			}
		}
		$html_end = FALSE;
		$txt_arr = [];
		$image_arr = [];
		$this->base_dir = './convert/';
		if (!is_dir($this->base_dir . 'images/')) {
			mkdir($this->base_dir . 'images/', 0777, TRUE);
		}
		if($this->read_file($handle)) {
			foreach($this->read_file($handle) as $key=>$l) {
				if(!$html_end && strpos($l, self::HTML_END_TAG) !== FALSE) {
					$html_end = TRUE;
					continue;
				}
				if($html_end) {
					if(strpos($l, 'Content-Type') !== FALSE) {
						$img_type = self::get_img_type($l);
						continue;
					}
					if(strpos($l, 'Content-Transfer-Encoding') !== FALSE) {
						continue;
					}
					if(strpos($l, 'Content-Location') !== FALSE) {
						preg_match("/(\{[^\}]+\}\.dat)/i", $l, $matches);
						$img_name = str_replace('.dat', '', $matches[1]);
						continue;
					}
					if(strpos($l, $sep) !== FALSE) {
						if(isset($n) && !empty($image_arr[$n])) {
							$base64 = implode('', $image_arr[$n]);
							$image_size[$img_name] = round(strlen($base64) / 1024, 2); //每张图片大小 单位kb
							$this->image_type[$img_name] = $img_type;
							$this->base64toimage($base64, $img_name, $img_type);
							unset($image_arr[$n]); //及时释放内存
						}
						$n = $key;
					} else {
						$image_arr[$n][] = $l;
					}
				} else {
					$txt_arr[] = $l;
				}
			}
		}
		fclose($handle);
		//$txt_arr第一个元素包含头部信息
		preg_match('/\d{4}-\d{2}-\d{2}<\/td><\/tr>/', $txt_arr[0], $b);
		$len = strpos($txt_arr[0], $b[0]) + strlen($b[0]);
		//头文件1
		$html_header = substr($txt_arr[0], 0, $len) . PHP_EOL;
		//头文件2
		$html_header_other = str_replace('height:24px;line-height:24px;padding-left:10px;margin-bottom:5px;', 'padding-left:10px;', $html_header);
		$html_header_other = str_replace('<tr><td><div style=padding-left:10px;>&nbsp;</div></td></tr>', '', $html_header_other);
		$html_header_other = preg_replace('/日期: \d{4}-\d{2}-\d{2}/', '&nbsp;', $html_header_other);
		$txt_arr[0] = substr($txt_arr[0], $len, strlen($txt_arr[0]));
		$qq_text = implode('', $txt_arr);
		if(filesize($chat_file) / 1024 / 1024 < $this->per) {
			$this->convert_name = str_replace('.mht', '', $chat_file);
			$qq_text = $this->src($qq_text);
			$qq_text = $html_header . $qq_text . self::HTML_END_TAG;
			file_put_contents($this->base_dir . $this->convert_name . '.html', $qq_text);
		} else {
			$qq_text_arr = explode(PHP_EOL, $qq_text);
			$image_size_sum = 0; //计算图片总大小
			$lines = []; //分隔完的文件 最后一条记录在$qq_text_arr中的所在行数
			foreach ($qq_text_arr as $l => $val) {
				//根据图片大小总和按照per大小 生成若干小文件
				if (strpos($val, 'src="{') !== FALSE) {
					preg_match_all('/src="(\{[^\}]+\})/i', $val, $matches);
					foreach ($matches[1] as $v) {
						if (isset($image_size[$v])) {
							$image_size_sum += $image_size[$v];
						}
					}
					if ($image_size_sum > $this->per * 1024) { //per生成每个文件的大小  第107行开始处理最后一个小文件(最后一个小文件有可能不够per * 1024)
						$lines[] = $l; //记录每个文件最后一行记录的行号
						$image_size_sum = 0;
					}
				}
			}
			//遍历分隔的文件，生成html文件
			foreach ($lines as $key => $line) {
				$html = '';
				$this->convert_name = $file_name_prefix . ($key + 1); //文件名从1开始命名
				//第一个小文件（头部和其他分割的头部有一些区别）
				if ($key == 0) {
					for ($i = 0; $i <= $line; $i++) {
						$html .= $qq_text_arr[$i] . PHP_EOL;
					}
					$html = $this->src($html);
					$new_content = $html_header . $html . self::HTML_END_TAG . PHP_EOL;
					//其余小文件
				} else {
					for ($i = $lines[$key - 1] + 1; $i <= $line; $i++) { //$lines[$key - 1]为上一个文件的最后一条所在行数，+1为当前文件第一条所在行数。 循环该文件第一条到最后一条记录
						$html .= $qq_text_arr[$i] . PHP_EOL;
					}
					$html = $this->src($html);
					$new_content = $html_header_other . $html . self::HTML_END_TAG . PHP_EOL;
				}
				file_put_contents($this->base_dir . $this->convert_name . '.html', $new_content);
			}
			//最后一个小文件（存在分割到最后，不够per * 1024的大小，总行数和分割之后最后一个文件的最后一条记录的行数对比）
			if ($line != count($qq_text_arr)) {
				$html = '';
				$this->convert_name = $file_name_prefix . ($key + 2); //这里的key 延用上面87行的key
				for ($i = $lines[$key] + 1; $i <= count($qq_text_arr) - 1; $i++) { //$lines[$key]为最后一个分割的文件最后一条所在行数，+1为当前文件第一条所在行数。 循环该文件第一条到最后一条记录
					$html .= $qq_text_arr[$i] . PHP_EOL;
				}
				$html = $this->src($html);
				$new_content = $html_header_other . $html . self::HTML_END_TAG . PHP_EOL;
				file_put_contents($this->base_dir . $this->convert_name . '.html', $new_content);
			}
		}
		//有些不存在的图片可能在多个分割文件都有出现，这里只显示1条，要显示全部屏蔽这行即可
		if($this->dat_arr) {
			echo PHP_EOL . "不存在的图片：" . PHP_EOL;
			foreach ($this->dat_arr as $dir=>$dat) {
				foreach($dat as $k=>$val) {
					echo $val . ' => ' . $k . PHP_EOL; //去重。 给出提示，在哪个分割的文件内（根据提示，可以手动去pc端或者手机端 定位到不显示的图片或表情，然后将其保存到images文件夹下，文件名用$val+'.gif'命名，包含'{','}'）
				}
			}
		}
		echo PHP_EOL . 'mht文件转换完成，总耗时:' . round(microtime(1) - $s, 3) . "s" . PHP_EOL;
		$unit = ['b', 'kb', 'mb', 'gb'];
		$size = memory_get_usage() - $m;
		echo PHP_EOL . '占用内存 '.round($size / pow(1024, ($i = floor(log($size, 1024)))), 2).' '.$unit[$i]. PHP_EOL;
	}

	//获取图片格式
	static private function get_img_type($image_type) {
		if (strpos($image_type, 'gif') !== FALSE) {
			$ext = '.gif';
		}
		if (strpos($image_type, 'png') !== FALSE) {
			$ext = '.png';
		}
		if (strpos($image_type, 'jpeg') !== FALSE || strpos($image_type, 'jpg') !== FALSE) {
			$ext = '.jpg';
		}
		return $ext;
	}

	private function base64toimage($base64, $k, $img_type) {
		file_put_contents($this->base_dir . 'images/' . $k . $img_type, base64_decode($base64));
	}

	private function src($html) {
		return preg_replace_callback('/src="(\{[^"]+)"/i', function($m) {
			$s = str_replace('.dat', '', $m[1]);
			if (isset($this->image_type[$s])) {
				return 'src="./images/' . $s . $this->image_type[$s] . '"';
			} else { //不存在的图片转成gif
				$this->dat_arr[$this->base_dir][$s] = str_replace('./\\', '', $this->base_dir . $this->convert_name) . '.html';
				return 'src="./images/' . $s . '.gif"';
			}
		}, $html);
	}

	private function read_file($handle) {
		while($line = fgets($handle)) {
			yield $line;
		}
	}

	private function read_dir() {
		$files = [];
		foreach(glob('./*') as $file) {
			if(strpos($file, '.mht') !== FALSE)
				$files[] = $file;
		}
		return str_replace('./', '', current($files));
	}
}

$opt = getopt("s:");
if(isset($opt['s']) && intval($opt['s'])) {
	$Spliter = new QQMhtSpliter2(intval($opt['s']));
} else {
	$Spliter = new QQMhtSpliter2();
}
$Spliter->convert();
