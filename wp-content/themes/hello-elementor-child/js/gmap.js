// Wrapped in an IIFE so it binds to the jQuery instance present when this file
// loads (our local 1.11.1), independent of WordPress's noConflict jQuery that
// loads later in the footer. `area` is read from the global set by the page.
(function ($) {
$(function(){

	var map;
	var markers1 = new Array();
	var markers2 = new Array();
	var markers3 = new Array();
	var markers4 = new Array();
	
	var str_sales = "Sales / Servicing";
	var str_production = "Production / Distribution";
	var str_research = "Research &amp; Development";
	var str_hq = "HQ / Administration";
	
	var url = $(location).attr("href");
	
	if(url.match("italian")){
		str_sales = "Vendite/Assistenza";
		str_production = "Produzione/Distribuzione";
		str_research = "Ricerca e sviluppo";
		str_hq = "Sede centrale/Amministrazione";
	}
	else if(url.match("dutch")){
		str_sales = "Verkoop / Service";
		str_production = "Productie / Distributie";
		str_research = "Onderzoek & Ontwikkeling";
		str_hq = "Hoofdkantoor / Bestuur";
	}
	else if(url.match("french")){
		str_sales = "Vente / Service";
		str_production = "Production / Distribution";
		str_research = "Recherche et développement";
		str_hq = "Siège social / Administration";
	}
	else if(url.match("portuguese")){
		str_sales = "Vendas / assistência";
		str_production = "Produção / distribuição";
		str_research = "Investigação edesenvolvimento";
		str_hq = "Sede / Administração";
	}
	else if(url.match("spanish")){
		str_sales = "Ventas / Atención al cliente";
		str_production = "Producción / Distribución";
		str_research = "Investigación ydesarrollo";
		str_hq = "Sede central / Administración";
	}

	
	
	var currentInfoWindow = null;	//最後に開いた情報ウィンドウを記憶
	
	var __iconBase = (typeof window !== 'undefined' && window.ARKRAY_MAP_ICON_BASE) ? window.ARKRAY_MAP_ICON_BASE : 'https://www.arkray.global/english/common/img/';
	var marker_icon = new Array(__iconBase + 'maker_red.png', __iconBase + 'maker_orange.png', __iconBase + 'maker_green.png', __iconBase + 'maker_blue.png');
	//マーカーの情報
	// 本社・管理拠点
	data1 = new Array();
	data1.push({
		lat: '35.036799',
		lng: '135.756443',
		url: 'group02.html#ARKRAYInc',
		title: 'ARKRAY, Inc.',
		zindexorder: 100
	});
	data1.push({
		lat: '35.006019',
		lng: '135.759293',
		url: 'group02.html#UniversalHealthwareInc',
		title: 'Universal Healthware, Inc.',
		zindexorder: 100
	});
	data1.push({
		lat: '35.687269',
		lng: '139.728018',
		url: 'group02.html#ARKRAYMarketingInc',
		title: 'ARKRAY Marketing, Inc.',
		zindexorder: 10
	});
	data1.push({
		lat: '35.036799',
		lng: '135.756143',
		url: 'group02.html#ARKRAYGlobalBusinessInc',
		title: 'ARKRAY Global Business, Inc.',
		zindexorder: 10
	});
	data1.push({
		lat: '1.317230',
		lng: '103.843576',
		url: 'group03.html#ARKRAYPARTNERS',
		title: 'ARKRAY&PARTNERS Pte. Ltd.',
		zindexorder: 10
	});
	data1.push({
		lat: '44.866484',
		lng: '-93.355606',
		url: 'group05.html#ARKRAYAmericaInc',
		title: 'ARKRAY America, Inc.',
		zindexorder: 10
	});
	
	// 研究開発拠点
	data2 = new Array();
	data2.push({
		lat: '35.036799',
		lng: '135.756443',
		url: 'group02.html#ARKRAYIncKyotoLaboratory',
		title: 'ARKRAY, Inc. Kyoto Laboratory'
	});
	data2.push({
		lat: '32.74732359594623',
		lng: '129.87483531211208',
		url: 'group02.html#ARKRAYNagasaki',
		title: 'ARKRAY Nagasaki Development Center, Inc.'
	});
	data2.push({
		lat: '1.303987',
		lng: '103.792567',
		url: 'group03.html#ARKRAYPARTNERSPteLtdSingaporeLaboratory',
		title: 'ARKRAY&PARTNERS Pte.Ltd. Singapore Laboratory'
	});
	data2.push({
		lat: '37.349523',
		lng: '126.953490',
		url: 'group03.html#ARKRAYGlobalBusinessIncKoreanDevelopmentCenter',
		title: 'ARKRAY Global Business, Inc. Korean Development Center',
	});
	data2.push({
		lat: '18.593928',
		lng: '73.757249',
		url: 'group03.html#ARKRAYHealthcarePvtLtd',
		title: 'ARKRAY Healthcare Pvt. Ltd.'
	});
	data2.push({
		lat: '18.593928',
		lng: '73.757249',
		url: 'group03.html#ARKRAYHealthcarePvtLtd',
		title: 'ARKRAY Healthcare Pvt. Ltd.'
	});
	data2.push({
		lat: '34.6890813',
		lng: '135.4953552',
		url: 'group02.html#OsakaDevelopmentCenter',
		title: 'ARKRAY Osaka Development Center, Inc.'
	});

	
	// 生産・物流拠点
	data3 = new Array();
	data3.push({
		lat: '34.895600',
		lng: '136.171004',
		url: 'group02.html#ARKRAYFactoryInc',
		title: 'ARKRAY Factory, Inc.'
	});
	data3.push({
		lat: '30.737305',
		lng: '121.009245',
		url: 'group03.html#ARKRAYFactoryPinghu',
		title: 'ARKRAY Factory Pinghu, Inc.'
	});
	data3.push({
		lat: '14.134091',
		lng: '121.133976',
		url: 'group03.html#ARKRAYIndustryInc',
		title: 'ARKRAY Industry, Inc.'
	});
	data3.push({
		lat: '14.408576',
		lng: '120.864767',
		url: 'group03.html#ARKRAYIndustryWestInc',
		title: 'ARKRAY Industry West, Inc.'
	});
	data3.push({
		lat: '21.092560',
		lng: '72.852264',
		url: 'group03.html#ARKRAYHealthcarePvtLtdSurat',
		title: 'ARKRAY Healthcare Pvt. Ltd. Surat Factory'
	});
	
	data3.push({
		lat: '44.866634',
		lng: '-93.356620',
		url: 'group05.html#ARKRAYFactoryUSAInc',
		title: 'ARKRAY Factory USA, Inc.'
	});
	data3.push({
		lat: '53.459302',
		lng: '-6.211069',
		url: 'group04.html#ARKRAYIrelandLtd',
		title: 'ARKRAY Ireland Ltd.'
	});
	
	// 営業・サービス拠点
	data4 = new Array();
	data4.push({
		lat: '43.066732',
		lng: '141.345983',
		url: 'group02.html#ARKRAYMarketingInc',
		title: 'ARKRAY Marketing, Inc. Sapporo SSO'
	});
	data4.push({
		lat: '38.270730',
		lng: '140.861307',
		url: 'group02.html#ARKRAYMarketingInc',
		title: 'ARKRAY Marketing, Inc. Sendai SSO (Office I&bull;II)'
	});
	data4.push({
		lat: '36.233443',
		lng: '137.945509',
		url: 'group02.html#ARKRAYMarketingInc',
		title: 'ARKRAY Marketing, Inc. Nagano SSO'
	});
	data4.push({
		lat: '35.024636',
		lng: '138.478687',
		url: 'group02.html#ARKRAYMarketingInc',
		title: 'ARKRAY Marketing, Inc. Shizuoka SSO'
	});
	data4.push({
		lat: '35.9020191',
		lng: '139.626146',
		url: 'group02.html#ARKRAYMarketingInc',
		title: 'ARKRAY Marketing, Inc. Saitama SSO'
	});
	data4.push({
		lat: '35.687269',
		lng: '139.728018',
		url: 'group02.html#ARKRAYMarketingInc',
		title: 'ARKRAY Marketing, Inc. Tokyo SSO (Office I&bull;II)'
	});
	data4.push({
		lat: '35.510837',
		lng: '139.616910',
		url: 'group02.html#ARKRAYMarketingInc',
		title: 'ARKRAY Marketing, Inc. Yokohama SSO'
	});
	data4.push({
		lat: '35.171577',
		lng: '136.929931',
		url: 'group02.html#ARKRAYMarketingInc',
		title: 'ARKRAY Marketing, Inc. Nagoya SSO'
	});
	data4.push({
		lat: '34.697209',
		lng: '135.509776',
		url: 'group02.html#ARKRAYMarketingInc',
		title: 'ARKRAY Marketing, Inc. Osaka SSO (Office I&bull;II)'
	});
	data4.push({
		lat: '34.949368',
		lng: '135.752833',
		url: 'group02.html#ARKRAYMarketingInc',
		title: 'ARKRAY Marketing, Inc. Kyoto SSO'
	});
	data4.push({
		lat: '34.381166',
		lng: '132.468463',
		url: 'group02.html#ARKRAYMarketingInc',
		title: 'ARKRAY Marketing, Inc. Hiroshima SSO'
	});
	data4.push({
		lat: '33.830233',
		lng: '132.789962',
		url: 'group02.html#ARKRAYMarketingInc',
		title: 'ARKRAY Marketing, Inc. Matsuyama SSO'
	});
	data4.push({
		lat: '33.597727',
		lng: '130.409013',
		url: 'group02.html#ARKRAYMarketingInc',
		title: 'ARKRAY Marketing, Inc. Fukuoka SSO (Office I&bull;II)'
	});
	data4.push({
		lat: '31.543647',
		lng: '130.538191',
		url: 'group02.html#ARKRAYMarketingInc',
		title: 'ARKRAY Marketing, Inc. Kagoshima SSO'
	});
	data4.push({
		lat: '34.895600',
		lng: '136.171004',
		url: 'group02.html#ARKRAYInfinityInc',
		title: 'ARKRAY Infinity, Inc.'
	});
	data4.push({
		lat: '35.036799',
		lng: '135.756443',
		url: 'group02.html#KaradaLabInc',
		title: 'Karada Lab, Inc.'
	});
	data4.push({
		lat: '31.19137',
		lng: '121.4215451',
		url: 'group03.html#ARKRAYMarketingShanghaiInc',
		title: 'ARKRAY Marketing Shanghai, Inc.'
	});
	data4.push({
		lat: '39.905689',
		lng: '116.450677',
		url: 'group03.html#ARKRAYMarketingShanghaiBeijingSalesandServiceOffice',
		title: 'ARKRAY Marketing Shanghai, Inc. Beijing Sales and Service Office'
	});
	data4.push({
		lat: '37.520065',
		lng: '126.930759',
		url: 'group03.html#ARKRAYGlobalBusinessIncSeoulSalesandServiceOffice',
		title: 'ARKRAY Global Business, Inc. Seoul Sales and Service Office'
	});
	data4.push({
		lat: '1.317230',
		lng: '103.843576',
		url: 'group03.html#ARKRAYPARTNERS',
		title: 'ARKRAY&PARTNERS Pte. Ltd.'
	});
	data4.push({
		lat: '-6.213130',
		lng: '106.831048',
		url: 'group03.html#PTARKRAY',
		title: 'PT. ARKRAY'
	});
	data4.push({
		lat: '14.416408',
		lng: '121.043926',
		url: 'group03.html#ARKRAYCoLtdInc',
		title: 'ARKRAY Co. Ltd., Inc.'
	});
	data4.push({
		lat: '19.079434',
		lng: '72.842577',
		url: 'group03.html#ARKRAYHealthcarePvtLtd',
		title: 'ARKRAY Healthcare Pvt. Ltd.'
	});
	data4.push({
		lat: '52.318536',
		lng: '4.871327',
		url: 'group04.html#ARKRAYEuropeBV',
		title: 'ARKRAY Europe, B.V.'
	});
	data4.push({
		lat: '44.866484',
		lng: '-93.355606',
		url: 'group05.html#ARKRAYUSAInc',
		title: 'ARKRAY USA, Inc.'
	});
	data4.push({
		lat: '44.866484',
		lng: '-93.355606',
		url: 'group05.html#USARKRAYInc',
		title: 'U.S.ARKRAY, Inc.'
	});
	data4.push({
		lat: '25.812239',
		lng: '-80.362822',
		url: 'group05.html#ARKRAYUSAIncMiamibranch',
		title: 'ARKRAY USA, Inc. Miami branch'
	});
	data4.push({
		lat: '21.050117',
		lng: '105.782285',
		url: 'group03.html#ArkrayVietnamCoLtd',
		title: 'Arkray Vietnam Co., Ltd.'
	});
	data4.push({
		lat: '51.660508',
		lng: '-0.915205',
		url: 'group04.html#ARKRAYLtd',
		title: 'ARKRAY Ltd.'
	});
	data4.push({
		lat: '45.600654',
		lng: '9.361161',
		url: 'group04.html#ARKRAYItaliaSRL',
		title: 'ARKRAY Italia S.R.L.'
	});
	data4.push({
		lat: '41.372886',
		lng: '2.078611',
		url: 'group04.html#ARKRAYEspanaSAU',
		title: 'ARKRAY España S.A.U.'
	});
	data4.push({
		lat: '38.708140',
		lng: '-9.1432421',
		url: 'group04.html#ARKRAYEspanaSAUPortugalBranch',
		title: 'ARKRAY España S.A.U. Portugal Branch'
	});
	data4.push({
		lat: '50.846557',
		lng: '4.359621',
		url: 'group04.html#ARKRAYEuropeBVARKRAYBelgiumBranch',
		title: 'ARKRAY Europe, B.V. ARKRAY Belgium Branch'
	});
	data4.push({
		lat: '19.395444',
		lng: '-99.171727',
		url: 'group05.html#ARKRAYLabMexicoSAdeCV',
		title: 'ARKRAY Lab Mèxico, S.A. de C.V.'
	});
	data4.push({
		lat: '13.727034779374558',
		lng: '100.57816186013945',
		url: 'group03.html#ARKRAYThailandCoLtd',
		title: 'ARKRAY (Thailand) Co., Ltd.'
	});

	function map_canvas() {
		//地図の初期位置
		var zoom_num = 3;
		var latlng;
		if( area == "World"){
			latlng = new google.maps.LatLng(data1[0].lat, data1[0].lng);
			zoom_num = 1;
		}
		else if( area == "Japan"){
			latlng = new google.maps.LatLng(38.687269, 139.728018);
			zoom_num = 5;
		}
		else if( area == "Asia"){
			latlng = new google.maps.LatLng(21.175167, 97.276863);
		}
		else if( area == "Europe"){
			latlng = new google.maps.LatLng(52.138717, 21.023811);
		}
		else if( area == "US"){
			latlng = new google.maps.LatLng(44.866484, -93.355606);
		}
		else{
			latlng = new google.maps.LatLng(35.036799, 135.756443);
		}
		var opts = {
			zoom: zoom_num,
			center: latlng,
			mapTypeId: google.maps.MapTypeId.ROADMAP
		};
	 
		//地図を表示させるエリアのidを指定
		map = new google.maps.Map(document.getElementById("gmap"), opts);
	 
	 	var popup_str;
		//マーカーを配置
		for (i = 0; i < data4.length; i++) {
			markers4[i] = new google.maps.Marker({
				position: new google.maps.LatLng(data4[i].lat, data4[i].lng),
				title: data4[i].title,
				icon: marker_icon[3],
				zIndex : 10,
				map: map
			});
			popup_str = popupStrAdjust(data4[i].url, data4[i].title, str_sales);
			attachMessage(markers4[i], popup_str);
		}
		for (i = 0; i < data3.length; i++) {
			clickable_flg = data3[i].url ? true : false;
			markers3[i] = new google.maps.Marker({
				position: new google.maps.LatLng(data3[i].lat, data3[i].lng),
				title: data3[i].title,
				icon: marker_icon[2],
				clickable: clickable_flg,
				zIndex : 10,
				map: map
			});
			popup_str = popupStrAdjust(data3[i].url, data3[i].title, str_production);
			attachMessage(markers3[i], popup_str);
		}
		for (i = 0; i < data2.length; i++) {
			clickable_flg = data2[i].url ? true : false;
			markers2[i] = new google.maps.Marker({
				position: new google.maps.LatLng(data2[i].lat, data2[i].lng),
				title: data2[i].title,
				icon: marker_icon[1],
				clickable: clickable_flg,
				zIndex : 10,
				map: map
			});
			popup_str = popupStrAdjust(data2[i].url, data2[i].title, str_research);
			attachMessage(markers2[i], popup_str);
		}
		for (i = 0; i < data1.length; i++) {
			clickable_flg = data1[i].url ? true : false;
			markers1[i] = new google.maps.Marker({
				position: new google.maps.LatLng(data1[i].lat, data1[i].lng),
				title: data1[i].title,
				icon: marker_icon[0],
				clickable: clickable_flg,
				zIndex : data1[i].zindexorder,
				map: map
			});
			popup_str = popupStrAdjust(data1[i].url, data1[i].title, str_hq);
			attachMessage(markers1[i], popup_str);
		}
	}

	function attachMessage(marker, msg) {
		var infoWnd = new google.maps.InfoWindow({content: msg});
		google.maps.event.addListener(marker, 'click', function(event) {
			if(currentInfoWindow) {
				currentInfoWindow.close();
			}
			infoWnd.open(marker.getMap(), marker);
			currentInfoWindow = infoWnd;
		});
	}
	
	function popupStrAdjust(url, title, sub) {
		if( url ){
			popup_str = "<a href='"+ url +"'>"+ title +"</a><br />"+ sub;
		}
		else{
			popup_str = data4[i].title +"<br />"+ sub;
		}
		return popup_str;
	}
	
	function removeMarkers(index) {
		for (i = 0; i < data1.length; i++) {
			markers1[i].setMap(null);
		}
		for (i = 0; i < data2.length; i++) {
			markers2[i].setMap(null);
		}
		for (i = 0; i < data3.length; i++) {
			markers3[i].setMap(null);
		}
		for (i = 0; i < data4.length; i++) {
			markers4[i].setMap(null);
		}
		switch (index){
		case 0:
			for (i = 0; i < data4.length; i++) {
				markers4[i] = new google.maps.Marker({
					position: new google.maps.LatLng(data4[i].lat, data4[i].lng),
					title: data4[i].title,
					icon: marker_icon[3],
					zIndex : 10,
					map: map
				});
				popup_str = popupStrAdjust(data4[i].url, data4[i].title, str_sales);
				attachMessage(markers4[i], popup_str);
			}
			for (i = 0; i < data3.length; i++) {
				markers3[i] = new google.maps.Marker({
					position: new google.maps.LatLng(data3[i].lat, data3[i].lng),
					title: data3[i].title,
					icon: marker_icon[2],
					zIndex : 10,
					map: map
				});
				popup_str = popupStrAdjust(data3[i].url, data3[i].title, str_production);
				attachMessage(markers3[i], popup_str);
			}
			for (i = 0; i < data2.length; i++) {
				markers2[i] = new google.maps.Marker({
					position: new google.maps.LatLng(data2[i].lat, data2[i].lng),
					title: data2[i].title,
					icon: marker_icon[1],
					zIndex : 10,
					map: map
				});
				popup_str = popupStrAdjust(data2[i].url, data2[i].title, str_research);
				attachMessage(markers2[i], popup_str);
			}
			for (i = 0; i < data1.length; i++) {
				markers1[i] = new google.maps.Marker({
					position: new google.maps.LatLng(data1[i].lat, data1[i].lng),
					title: data1[i].title,
					icon: marker_icon[0],
					zIndex : data1[i].zindexorder,
					map: map
				});
				popup_str = popupStrAdjust(data1[i].url, data1[i].title, str_hq);
				attachMessage(markers1[i], popup_str);
			}
			break;
		case 1:
			for (i = 0; i < data1.length; i++) {
				markers1[i] = new google.maps.Marker({
					position: new google.maps.LatLng(data1[i].lat, data1[i].lng),
					title: data1[i].title,
					icon: marker_icon[0],
					zIndex : data1[i].zindexorder,
					map: map
				});
				popup_str = popupStrAdjust(data1[i].url, data1[i].title, str_hq);
				attachMessage(markers1[i], popup_str);
			}
			break;
		case 2:
			for (i = 0; i < data2.length; i++) {
				markers2[i] = new google.maps.Marker({
					position: new google.maps.LatLng(data2[i].lat, data2[i].lng),
					title: data2[i].title,
					icon: marker_icon[1],
					map: map
				});
				popup_str = popupStrAdjust(data2[i].url, data2[i].title, str_research);
				attachMessage(markers2[i], popup_str);
			}
			break;
		case 3:
			for (i = 0; i < data3.length; i++) {
				markers3[i] = new google.maps.Marker({
					position: new google.maps.LatLng(data3[i].lat, data3[i].lng),
					title: data3[i].title,
					icon: marker_icon[2],
					map: map
				});
				popup_str = popupStrAdjust(data3[i].url, data3[i].title, str_production);
				attachMessage(markers3[i], popup_str);
			}
			break;
		case 4:
			for (i = 0; i < data4.length; i++) {
				markers4[i] = new google.maps.Marker({
					position: new google.maps.LatLng(data4[i].lat, data4[i].lng),
					title: data4[i].title,
					icon: marker_icon[3],
					map: map
				});
				popup_str = popupStrAdjust(data4[i].url, data4[i].title, str_sales);
				attachMessage(markers4[i], popup_str);
			}
			break;
		}
	}
	
	//地図描画を実行
	google.maps.event.addDomListener(window, 'load', map_canvas);

	$("ul.gmap_tab li").click(function(){
		$(this).parent().find("li").removeClass("ac");
		$(this).addClass("ac");
		var index = $("ul.gmap_tab li").index(this);
		removeMarkers(index);
	});

});
})(window.ARKRAY_JQ || window.jQuery);

