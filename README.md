# QQMhtSpliter
自己写的QQ聊天记录分割脚本，可以将pc端导出的mht格式的聊天文件分割成多个html文件
分割文件大小的计算方式：html文件以及该html文件内所有图片大小的总和

将导出的mht文件和脚本放在同一个目录
直接命令行运行 (默认分割之后每个文件40M)
  **php ./QQMhtSpliter.php**
或者加上参数 分割文件的大小（单位：M）
  **php ./QQMhtSpliter.php -s 30**
