<?php
class QQMhtSpliter {

	const html_end_tag = '</table></body></html>';
	private $per; //单位M，小于$per将不能分割

	private $dat_arr = []; //不存在的图片 保存在这里
	private $base_dir; //转换完成-目录
	private $convert_name = ''; //转换完成-文件名
	private $image_type = []; //每张图片的格式
	private $start = 0; //脚本执行时间

	public function __construct($per = 40) {
		$this->start = microtime(1);
		$this->per = $per;
	}

	public function convert() {
		$file_list = self::read_all('./', 'file');
		foreach($file_list as $k => $file_name) {
			if (strpos($file_name, '.mht') === FALSE) continue;
			$file_name_prefix = str_replace('.mht', '', $file_name) . '_';
			$whole_content = file_get_contents($file_name);
			//分割整个文件0为内容文字部分，1为图片部分
			$whole_arr = explode(self::html_end_tag, $whole_content);
			$txt = $whole_arr[0];
			$_7bit_len = strpos($txt, ':7bit');
			$arr = explode(PHP_EOL, substr($txt, 0, $_7bit_len));
			$sep = trim($arr[8]); //分割符
			$txt = trim(substr($txt, $_7bit_len + 5));
			preg_match('/\d{4}-\d{2}-\d{2}<\/td><\/tr>/', $txt, $b);
			$len = strpos($txt, $b[0]) + strlen($b[0]);
			//头文件1
			$html_header = substr($txt, 0, $len) . PHP_EOL;
			//头文件2
			$html_header_other = str_replace('height:24px;line-height:24px;padding-left:10px;margin-bottom:5px;', 'padding-left:10px;', $html_header);
			$html_header_other = str_replace('<tr><td><div style=padding-left:10px;>&nbsp;</div></td></tr>', '', $html_header_other);
			$html_header_other = preg_replace('/日期: \d{4}-\d{2}-\d{2}/', '&nbsp;', $html_header_other);
			//文件图片文件
			$image_data = $whole_arr[1];
			$this->base_dir = './convert_' . ($k + 1) . '/';
			if (!is_dir($this->base_dir . 'images/')) {
				mkdir($this->base_dir . 'images/', 0777, TRUE);
			}
			//将图片分割成数组
			$this->image_type = []; //批量时 初始化一下
			$image_data_arr = explode($sep, $image_data);
			//删除数组第一个和最后一个元素（没有用的两个元素）
			unset($image_data_arr[0]);
			array_pop($image_data_arr);
			//生成图片文件
			foreach ($image_data_arr as $val) {
				preg_match("/(\{[^\}]+\}\.dat)/i", $val, $matches);
				$img_k = str_replace('.dat', '', $matches[1]); //{sfasdf-asdfasdf-asdfa-sdfasdfasd}形式的
				$val = trim($val, PHP_EOL);
				$image_size[$img_k] = round(strlen($val) / 1024, 2); //每张图片大小 单位kb
				$img_type = self::get_img_type($val);
				$this->image_type[$img_k] = $img_type;
				self::base64toimage(trim($val, PHP_EOL) , $img_k, $img_type);
			}
			//分割聊天内容
			$qq_text = substr($txt, $len, strlen($txt)); //聊天内容
			if (filesize($file_name) / 1024 / 1024 < $this->per) {
				$this->convert_name = str_replace('.mht', '', $file_name);
				$qq_text = $this->src($qq_text);
				$qq_text = $html_header . $qq_text . self::html_end_tag;
				file_put_contents($this->base_dir . $this->convert_name . '.html', $qq_text);
				continue;
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
						$new_content = $html_header . $html . self::html_end_tag . PHP_EOL;
						//其余小文件
					} else {
						for ($i = $lines[$key - 1] + 1; $i <= $line; $i++) { //$lines[$key - 1]为上一个文件的最后一条所在行数，+1为当前文件第一条所在行数。 循环该文件第一条到最后一条记录
							$html .= $qq_text_arr[$i] . PHP_EOL;
						}
						$html = $this->src($html);
						$new_content = $html_header_other . $html . self::html_end_tag . PHP_EOL;
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
					$new_content = $html_header_other . $html . self::html_end_tag . PHP_EOL;
					file_put_contents($this->base_dir . $this->convert_name . '.html', $new_content);
				}
			}
		}
		//有些不存在的图片可能在多个分割文件都有出现，这里只显示1条，要显示全部屏蔽这行即可
		if($this->dat_arr) {
			// print_r($this->dat_arr);
			echo PHP_EOL . "不存在的图片：" . PHP_EOL;
			foreach ($this->dat_arr as $dir=>$dat) {
				foreach($dat as $k=>$val) {
					// echo $val . ' => ' . $dir . PHP_EOL; //配合163行 显示全部 不去重
					echo $val . ' => ' . $k . PHP_EOL; //去重。 给出提示，在哪个分割的文件内（根据提示，可以手动去pc端或者手机端 定位到不显示的图片或表情，然后将其保存到images文件夹下，文件名用$val+'.gif'命名，包含'{','}'）
				}
			}
		}
		echo PHP_EOL . 'mht文件转换完成，总耗时:' . round(microtime(1) - $this->start, 3) . "s" . PHP_EOL;
	}

	//遍历和脚本同目录下所有mht文件
	static private function read_all($dir, $type) {
		if (!is_dir($dir)) return FALSE;
		$arr = [];
		$handle = opendir($dir);
		if ($handle) {
			while (($fl = readdir($handle)) !== FALSE) {
				$temp = $dir . DIRECTORY_SEPARATOR . $fl;
				if ($type == 'dir' && is_dir($temp) && $fl[0] != '.') { //排除 . .. 开头的
					$arr[] = $temp;	
				}
				if ($type == 'file' && !is_dir($temp) && strpos($fl, '.mht') !== FALSE) { //只要mht文件
					$arr[] = $temp;
				}
			}
		}
		return $arr;
	}

	//将src=dat转换成图片地址
	private function src($html) {
		return preg_replace_callback('/src="(\{[^"]+)"/i', function($m) {
			$s = str_replace('.dat', '', $m[1]);
			if (isset($this->image_type[$s])) {
				return 'src="./images/' . $s . $this->image_type[$s] . '"';
			} else { //不存在的图片转成gif
				$this->dat_arr[$this->base_dir][$s] = str_replace('./\\', '', $this->base_dir . $this->convert_name) . '.html';
				// $this->dat_arr[$s][] = str_replace('./\\', '', $this->base_dir . $this->convert_name) . '.html';
				return 'src="./images/' . $s . '.gif"';
			}
		}, $html);
	}

	//base64转换成图片
	private function base64toimage($base64, $k, $img_type) {
		$base64 = trim(substr($base64, strpos($base64, $k . '.dat') + strlen($k . '.dat')));
		file_put_contents($this->base_dir . 'images/' . $k . $img_type, base64_decode($base64));
	}

	//获取图片格式
	static private function get_img_type($base64) {
		$str = substr($base64, 0, 25); //开始的几个字符
		if (strpos($str, 'gif') !== FALSE) {
			$ext = '.gif';
		}
		if (strpos($str, 'png') !== FALSE) {
			$ext = '.png';
		}
		if (strpos($str, 'jpeg') !== FALSE || strpos($str, 'jpg') !== FALSE) {
			$ext = '.jpg';
		}
		return $ext;
	}
}

$opt = getopt("s:");
if(isset($opt['s']) && intval($opt['s'])) {
	$Spliter = new QQMhtSpliter(intval($opt['s']));
} else {
	$Spliter = new QQMhtSpliter();
}
$Spliter->convert();
