# coding=UTF-8
#! /usr/local/bin/python3
import time
import re
import os
import sys
import base64


# dat替换为图片形式
def src(m):
    s = re.sub('.dat', '', m.group(1))
    if s in image_type_dict.keys():
      return 'src="./images/' + s + image_type_dict[s] + '"'
    else:
      dat_list.append('{}.html => {}不存在'.format(convert_name, s))
      return 'src="./images/' + s + '.gif"'
# 获取图片格式
def get_img_type(b64):
    s = b64[0:25]
    if s.find('gif') != -1:
        ext = '.gif'
    elif s.find('png') != -1:
        ext = '.png'
    elif s.find('jpg') != -1 or s.find('jpeg') != -1:
        ext = '.jpg'
    return ext
# base64转换为图片
def base64toimage(b64, k, img_type):
    c = b64.split(k + '.dat')[1].strip() #只留base64编码 其他删除
    c = base64.b64decode(c)
    with open(image_dir + k + img_type, 'wb') as f: #b 用于图片等
        f.write(c)
def read_all(dir, type):
    items_list = []
    for parent, dirnames, filenames in os.walk(dir):
        if type == 'dir' and parent != '.' and parent.find('images') == -1: #排除images文件夹
            items_list.append(parent + '/')
        if type == 'file':
            for filename in filenames:
                if filename[0] != '.' and filename.find('.mht') != -1: #只获取mht文件
                    items_list.append(filename)
    return items_list


start = time.time()
# 分割文件大小
per = input('请输入分割文件的大小（单位：M）：')
per = int(per)
# 分割符
split_str = '\n'
# 获取当前目录的mht文件列表
file_list = read_all('.', 'file')
dat_dict = {}
dat_list = []
convert_num = 0
for fl in file_list:
  convert_num = convert_num + 1
  base_dir = './convert_' + str(convert_num) + '/'
  image_dir = base_dir + 'images/'
  if not os.path.exists(image_dir):
      os.makedirs(image_dir, 0o777)
  qq_name = re.sub('.mht', '', fl)
  file_name_prefix = qq_name + '_'
  o_file = qq_name + '.mht'
  size = os.path.getsize(o_file)
  # 读取原文件
  with open(o_file, encoding="UTF-8") as f:
      qq = f.read()
  html_end = '</table></body></html>'
  # 分割文件
  content = qq.split(html_end)
  # 聊天内容(含头)
  txt_qq = content[0].strip() + html_end
  # 图片
  qq_image = content[1].strip()
  # 生成header header2
  ret = re.search('\\d{4}-\\d{2}-\\d{2}</td></tr>', txt_qq)
  split_date = ret.group()
  txt = txt_qq.split(split_date)
  header_str = txt[0] + split_date + split_str
  # 获取分割符sep
  for l in header_str.split(split_str):
      sep = l.strip()
      if sep.find('------=_NextPart_') != -1:
          break
  header_str = header_str.split('Content-Transfer-Encoding:7bit')[1].strip() + split_str
  header2_str = header_str.replace('height:24px;line-height:24px;padding-left:10px;margin-bottom:5px;', 'padding-left:10px;')
  header2_str = header2_str.replace('<tr><td><div style=padding-left:10px;>&nbsp;</div></td></tr>', '')
  header2_str = re.sub('日期: \\d{4}-\\d{2}-\\d{2}', '&nbsp;', header2_str)
  # 聊天内容
  qq_content = txt[1].replace(html_end, '').strip()
  # 图片列表
  qq_image_list = qq_image.split(sep)
  # 删除列表的第一个和最后一个
  qq_image_list.pop(0)
  qq_image_list.pop()
  image_size_dict = {}
  image_type_dict = {}
  # 生成图片文件
  for l in qq_image_list:
      dat = re.search('\\{[^\\}]+\\}\\.dat', l)
      dat_key = re.sub('\\.dat', '', dat.group())
      # 每张图片大小 单位kb
      image_size_dict[dat_key] = round(float(len(l)) / 1024, 2)
      img_type = get_img_type(l)
      image_type_dict[dat_key] = img_type
      base64toimage(l.strip(), dat_key, img_type)
  if size/1024/1024 < per:
      convert_name = base_dir + qq_name
      html = re.sub('src="(\\{[^"]+)"', src, qq_content)
      new_content = header_str + html + html_end + split_str
      with open(convert_name +'.html', 'w', encoding="UTF-8") as f:
          f.write(new_content)
      print(qq_name + ' 不需分割，将直接转换为html文件')
      continue
  else:
      # 分割聊天内容
      qq_content_list = qq_content.split(split_str)
      # 图片大小统计
      img_size_num = 0
      file_list = []
      pattern = re.compile('src="(\\{[^\\}]+\\})')
      for i, l in enumerate(qq_content_list): # l为每条记录
          # 根据图片大小总和按照$per大小 生成若干小文件
          if l.find('src="{') != -1:
              for v in pattern.findall(l): # v为dat名
                  if v in image_size_dict.keys():
                      img_size_num = img_size_num + image_size_dict[v]
                  if img_size_num > per * 1024:
                      # 记录每个文件开始的行号
                      file_list.append(i)
                      img_size_num = 0
      # 生成内容文件
      for k, v in enumerate(file_list):
          convert_name = base_dir +file_name_prefix + str(k + 1)
          html = ''
          # 第一个小文件
          if k == 0:
              for j in range(v+1):
                  html = html + qq_content_list[j] + split_str
              html = re.sub('src="(\\{[^"]+)"', src, html)
              new_content = header_str + html + html_end + split_str
          # 其他小文件
          else:
              for j in range(file_list[k - 1] + 1, v+1):
                  html = html + qq_content_list[j] + split_str
              html = re.sub('src="(\\{[^"]+)"', src, html)
              new_content = header2_str + html + html_end + split_str
          with open(convert_name +'.html', 'w', encoding="UTF-8") as f:
              f.write(new_content)
      # 最后一个小文件
      if v != len(qq_content_list):
          convert_name = base_dir + file_name_prefix + str(k + 2)
          html = ''
          for j in range(file_list[k] + 1, len(qq_content_list)):
              html = html + qq_content_list[j] + split_str
          html = re.sub('src="(\\{[^"]+)"', src, html)
          new_content = header2_str + html + html_end + split_str
          with open(convert_name +'.html', 'w', encoding="UTF-8") as f:
              f.write(new_content)
end = time.time()
print('不存在的图片：')
new_dat_list = list(dict.fromkeys(dat_list))
for i in new_dat_list:
  print(i)
print('{}个mht文件被转换，总耗时：{}s'.format(convert_num, end - start))
