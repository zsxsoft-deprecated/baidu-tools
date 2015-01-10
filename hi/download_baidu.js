(function(config){
	var request = require('request'),
		async = require('async'),
		fs = require('fs'),
		events = require('events');

	var getBdsToken = function (callback) {
	
		request({
			url: parseParam(config.getIndexUrl), 
			headers: {
				'Cookie': config.cookieString
			}
		}, function (error, response, body) {
			var qBdsToken = "";
			if (!error && response.statusCode == 200) {
				qBdsToken = body.split("window.qBdsToken=\"")[1];
				qBdsToken = qBdsToken.split("\"")[0];
				console.log(qBdsToken);
				callback(null, qBdsToken);
			} else {
				callback(error);
			}
		});
	
	}

	var getList = function (page, qBdsToken, callback) {

		request({
			url: parseParam(config.getDataUrl, page), 
			headers: {
				'Cookie': config.cookieString
			}
		}, function (error, response, body) {
			if (!error && response.statusCode == 200) {
				callback(null, qBdsToken, response, body);
			} else {
				callback(error);
			}
		})

	}

	var parseUrl = function (qBdsToken, response, body, callback) {
		var json = eval('(' + body + ')');
		var list = [];
		console.log("")
		for (var i = 0; i < json.data.count; i++) {
			list.push((function (data) {
				var res = data;
				res = res.split("blogid=\"")[1];
				res = res.split("\"")[0];
				return res;
			})(json.data.items[i]));
		}
		console.log(list);
		callback(null, qBdsToken, list);

	}

	var downloadContent = function(qBdsToken, listDownload, callback) {

		var buildFunction = function(url) {
			return function (callback) {
				var proxy = new events.EventEmitter();

				var runFunction = function () {
					request({
						url: parseParam(config.getArticleUrl, null, url), 
						headers: {
							'Cookie': config.cookieString
						}
					}, function (error, response, body) {
						if (!error && response.statusCode == 200) {
							try {
								var title = body.split('<h2 class="title content-title">')[1];
								title = title.split("</h2>")[0];

								var content = body.split('<div id=content class="content mod-cs-content text-content clearfix">')[1];
								content = content.split('<div class="mod-tagbox clearfix">')[0];
										
								var da = body.split('<div class=content-other-info>')[1];
								da = da.split("</div>")[0];
								da = da.replace(/[^0123456789 -]/ig, "");
								da = da.trim();

								fs.writeFile("./dir/" + da + " - " + title.replace(/\?\!/ig, "") + ".txt", content);
								console.log(url + " - " + title);

								proxy = null;
								callback(null);
							} catch (e) {
								proxy.emit("error", "");
							}
						} else {
							proxy.emit("error", "");
						}
					});
				}

				proxy.on("error", runFunction);
				runFunction();
			}
		}

		var parallelList = [];
		for (var i = 0; i < listDownload.length; i++) {
			parallelList.push(buildFunction(listDownload[i]));
		}

		async.parallel(parallelList, function (err, results) {
			if (!err) {
				callback(null, qBdsToken, listDownload);
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
				getBdsToken(callback);
			},
			function (qBdsToken, callback) {
				getList(page, qBdsToken, callback);
			}, 
			function (qBdsToken, response, body, callback) {
				parseUrl(qBdsToken, response, body, callback);
			}, 
			function (qBdsToken, listDownload, callback) {
				downloadContent(qBdsToken, listDownload, callback);
			}/*,
			function (qBdsToken, listDownload, callback) {
				delArticle(qBdsToken, listDownload, callback);
			}*/
		], function (err, result) {
			runAsync(++page);
		});
	}
//			if (!error && response.statusCode == 200) {

	runAsync(9);

	
})({
	cookieString: '',
	getArticleUrl: "http://hi.baidu.com/{%username%}/item/{%blogid%}",
	getIndexUrl: "http://hi.baidu.com/{%username%}",
	getDataUrl: "http://hi.baidu.com/{%username%}?asyn=1&mode=data&page={%page%}&_{%timestamp%}=1&qing_request_source=new_request",
	deleteUrl: "http://hi.baidu.com/pub/submit/deleteblog",
	userName: "用户名"
});