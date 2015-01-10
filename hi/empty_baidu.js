(function(config){
	var request = require('request'),
		async = require('async'),
		fs = require('fs'),
		events = require('events'),
		Wind = require("wind");

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

	var delArticle = function (qBdsToken, listDownload, callback) {

		var subFunction = function (url) {
			request.post({
				url: config.deleteUrl,
				headers: {
					'Cookie': config.cookieString, 
				}, 
				form: {
					"qbid": url,
					"bdstoken": qBdsToken,
					"qing_request_source": "new_request"
				}
			}, function (error, response, body) {
				console.log(body);
				console.log("deleted: " + url);
			});
		}

		var runFunction = eval(Wind.compile("async", function (url) {
			$await(Wind.Async.sleep(2000));
			subFunction(url);
		}));

		var run = eval(Wind.compile("async", function () {
			for (var i = 0; i < listDownload.length; i++) {
				$await(runFunction(listDownload[i]));
			}
			callback(null);
		}));

		run().start();

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
				delArticle(qBdsToken, listDownload, callback);
			}
		], function (err, result) {
			runAsync(page);
		});
	}

	runAsync(1);

	
})({
	cookieString: 'COOKIE信息',
	getArticleUrl: "http://hi.baidu.com/{%username%}/item/{%blogid%}",
	getIndexUrl: "http://hi.baidu.com/{%username%}",
	getDataUrl: "http://hi.baidu.com/{%username%}?asyn=1&mode=data&page={%page%}&_{%timestamp%}=1&qing_request_source=new_request",
	deleteUrl: "http://hi.baidu.com/pub/submit/deleteblog",
	userName: "用户名"
});