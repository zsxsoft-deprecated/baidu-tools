(function(config){
	var request = require('request'),
		async = require('async'),
		fs = require('fs'),
		events = require('events'),
		iconv = require('iconv-lite');


	var downloadTiebaPage = function (page, callback) {

		request.get({
			url: parseParam(config.getList, page), 
			headers: {
				'Cookie': config.cookieString
			}, 
			encoding: null
		}, function (error, response, body) {
			if (!error && response.statusCode == 200) {
				
				//var urlRegExp = /class="for_reply_context" style="text-decoration:none;" href="(.+?)"/ig;  // my_reply for this
				var urlRegExp = /href="(.+?)" class="thread_title"/ig; // my_tie for this
				var str = iconv.decode(body, 'gbk');

				var list = [];
				while ((result = urlRegExp.exec(str)) != null) {
					list.push(result[1]);
				}

				console.log(list);
				callback(null, list);
			} else {
				callback(error);
			}
		})

	}

	var downloadTiebaList = function (list, callback) {

		var buildFunction = function (url) {
			return function (callback) {
				var proxy = new events.EventEmitter();
				proxy.on("run", function () {
					request.get({
						url: "http://tieba.baidu.com" + url,
						headers: {
							'Cookie': config.cookieString
						}, 
						encoding: null
					}, function (error, response, body) {
						if (!error && response.statusCode == 200) {
							
							var data = iconv.decode(body, "utf-8");

							var threadTitleRegEx = /<h1 class="core_title_txt.+>(.+?)<\/h1>/i;
							var threadContentRegEx = /d_post_content_bold">([\d\D]+?)<\/div>[\d\D]+?j_reply_data">(.+?)<\/span>/i;
							var threadBarRegEx = /<返回(.+?)吧<\/a>/i;
							var threadFidRegEx = /fid:'(.+?)',/i;
							var threadIDRegEx = /\/p\/(\d+)\?pid=(\d+)/i;
							var threadCIDRegEx = /cid=(\d+)/i;

							var tid, fid, pid, cid, title, bar, content, time;
							try {
								tid = data.match(threadIDRegEx)[1];
								pid = data.match(threadIDRegEx)[2];
								cid = url.match(threadCIDRegEx)[1];
								fid = data.match(threadFidRegEx)[1];
								title = data.match(threadTitleRegEx)[1];
								bar = data.match(threadBarRegEx)[1];
								content = data.match(threadContentRegEx)[1];
								time = data.match(threadContentRegEx)[2];
							}
							catch (e) {
								// do nothing;
							}
							var output = [tid, pid, cid, fid, bar, title, time].join("|"); // reply for cid

							console.log(output);
							fs.writeFile('./threads/' + tid + "-" + pid + ".txt", output + "\n" + content, "utf-8");
							fs.appendFile('./thread.txt', output + "\n", 'utf-8');

							callback(null, list);

						} else {
							proxy.emit("run");
						}
					})
				});
				proxy.emit("run");
			}
		}

		var parallelList = [];
		for (var i = 0; i < list.length; i++) {
			parallelList.push(buildFunction(list[i]));
		}

		async.parallel(parallelList, function (err, results) {
			if (!err) {
				callback(null);
			} else {
				callback(err);
			}
		})
	}


	var parseParam = function (string, page, blogid) {
		if (!page) page = 1;
		string = string.replace(/\{\%username\%\}/ig, config.userName);
		string = string.replace(/\{\%timestamp\%\}/ig, new Date().getTime());
		string = string.replace(/\{\%page\%\}/ig, page);
		string = string.replace(/\{\%blogid\%\}/ig, blogid);
		return string;
	}

	var runAsync = function (page) {
		async.waterfall([
			function (callback) {
				downloadTiebaPage(page, callback);
			},
			function (list, callback) {
				downloadTiebaList(list, callback)
			}
		], function (err, result) {
			runAsync(++page);
		});
	}
//			if (!error && response.statusCode == 200) {

	runAsync(1); //页数

	
})({
	cookieString: '这里填入COOKIE',
	getList: 'http://tieba.baidu.com/i/i贴吧的ID/my_tie?&pn={%page%}',
	userName: "用户名"
});

/* 
这里是针对主题贴进行抓取操作
如要抓取回复，把my_tie换my_reply，thread.txt换reply.txt，然后20和21行的注释交换即可。
*/