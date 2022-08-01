# QQMhtSpliter
QQ聊天记录能导出图片的就只有mht格式的（MacOS好像没有导出功能），文件越大打开的速度就越慢，如果聊天记录很大，可能就打不开了，就算打开了，页面也会超级长，浏览起来不方便。
所以自己就写了这个脚本。我自己用这个脚本已经好几年了，貌似没什么问题，还是比较方便的。

分割文件大小的计算方式：html文件以及该html文件内所有图片大小的总和

将导出的mht文件和脚本放在同一个目录
直接命令行运行 (默认分割之后每个文件40M)

  **php ./QQMhtSpliter.php**
  
或者加上参数 分割文件的大小（单位：M）

  **php ./QQMhtSpliter.php -s 30**

关于 **不存在的图片** 的解释
 不存在的图片指聊天记录中没有接收成功的图片，或者只在手机上显示的一些表情（pc端不支持显示的），还有一些pc端的表情导出了 但是没有对应的base64的编码的，是.dat形式的
 
 有些不存在的图片可能在多个分割文件都有出现，这里只显示1条，要显示全部屏蔽这行即可

 $this->dat_arr = array_unique($this->dat_arr); 
 
 根据提示，可以手动去pc端或者手机端 定位到不显示的图片或表情，然后将其保存到images文件夹下，文件名以 **{F5F57D4B-43A9-4eb8-9DA5-D3431C5952FB}.gif** 这种形式命名，因为不确定图片的格式，统一指定为gif格式，保存之后，所有文件包含此图片的就都会显示出来了。
 
