/**
 * trad-check.js — spots Simplified Chinese characters in text boxes.
 *
 * The heading fonts are Traditional-Chinese fonts: a Simplified
 * character such as 帅 is not in them, so the browser draws just that
 * one character in a plain fallback font and the heading looks broken.
 * Under any box inside [data-trad-check] this shows which characters are
 * Simplified and offers to switch them to Traditional in one tap.
 *
 * Only characters with ONE Traditional form are listed (发 = 發 or 髮,
 * 后 = 後 or 后 … are left out), so the switch never picks the wrong one.
 */
(function () {
  var PAIRS =
    '帅帥坛壇庙廟宫宮龙龍凤鳳会會华華马馬门門东東书書长長个個们們来來时時说說' +
    '对對与與为為这這过過还還进進远遠运運边邊达達选選关關开開问問间間闻闻闻聞' +
    '见見观觀规規视視亲親记記设設许許请請让讓识識语語读讀谢謝课課谁誰诚誠诞誕' +
    '贺賀赠贈财財贵貴费費资資买買卖賣实實宝寶岁歲圣聖灵靈礼禮祷禱护護报報' +
    '欢歡乐樂灯燈烛燭爱愛庆慶节節园園图圖团團围圍国國场場堂堂坐坐处處备備' +
    '传傳众眾优優伤傷价價体體们們师師归歸临臨丰豐义義乡鄉习習农農写寫军軍' +
    '办辦动動务務区區医醫协協单單卫衛厅廳县縣参參双雙变變叶葉号號' +
    '吗嗎启啟员員呜嗚响響鸣鳴听聽营營乐樂荣榮药藥莲蓮万萬两兩严嚴丧喪丽麗' +
    '举舉么麼乌烏乔喬习習孙孫学學宁寧宽寬寿壽对對导導层層岛島币幣带帶帮幫' +
    '广廣庄莊库庫应應张張弥彌强強录錄总總恋戀恶惡愿願戏戲战戰户戶扬揚' +
    '执執扩擴扫掃护護报報拥擁择擇择擇据據捐捐换換损損敌敵数數断斷无無旧舊' +
    '显顯晓曉暂暫术術机機杀殺杂雜权權条條来來杨楊极極构構枪槍样樣桥橋' +
    '梦夢检檢楼樓欧歐残殘毕畢气氣汉漢汤湯沟溝没沒泪淚泽澤洁潔浅淺济濟' +
    '浓濃润潤涨漲渐漸湾灣湿濕满滿滨濱灭滅炉爐点點热熱爷爺牵牽状狀' +
    '独獨狮獅猪豬献獻环環现現玛瑪电電画畫畅暢疗療盖蓋监監盘盤' +
    '矿礦码碼确確祸禍福福离離种種积積称稱稳穩穷窮竞競笔筆简簡类類' +
    '粮糧系系纪紀约約红紅纯純纸紙线線练練组組细細经經结結绝絕统統继繼' +
    '绩績续續维維综綜绿綠缘緣网網罗羅职職联聯肃肅胜勝脑腦脸臉艺藝节節' +
    '苏蘇荐薦虽雖蚁蟻虾蝦补補装裝观觀览覽计計认認训訓讨討议議' +
    '讲講论論访訪证證评評词詞试試话話询詢该該详詳误誤调調谈談谋謀谱譜' +
    '贝貝负負贡貢贤賢败敗货貨质質购購贴貼赵趙车車轨軌转轉轮輪软軟' +
    '较較载載辆輛辈輩辉輝输輸辞辭迁遷迈邁还還这這连連迟遲适適邓鄧郑鄭' +
    '邮郵针針钱錢铁鐵银銀销銷锁鎖错錯锦錦键鍵镇鎮长長闭閉闲閒阅閱' +
    '队隊阳陽阴陰际際陆陸陈陳险險随隨隐隱难難雾霧静靜顶頂项項顺順' +
    '顾顧领領频頻题題颜顏风風飞飛饭飯饮飲馆館驻駐验驗鱼魚鸟鳥鸡雞麦麥齐齊';
  var MAP = {};
  for (var i = 0; i + 1 < PAIRS.length; i += 2) {
    if (PAIRS[i] !== PAIRS[i + 1]) MAP[PAIRS[i]] = PAIRS[i + 1];
  }

  function found(text) {
    var out = [];
    for (var ch of text) if (MAP[ch] && out.indexOf(ch) < 0) out.push(ch);
    return out;
  }
  function convert(text) {
    var s = '';
    for (var ch of text) s += MAP[ch] || ch;
    return s;
  }

  function watch(box) {
    var note = document.createElement('p');
    note.className = 'help trad-note';
    note.hidden = true;
    box.insertAdjacentElement('afterend', note);
    function check() {
      var chars = found(box.value);
      if (!chars.length) { note.hidden = true; note.textContent = ''; return; }
      note.hidden = false;
      note.innerHTML = '';
      var msg = document.createElement('span');
      msg.textContent = '⚠️ 簡體字 Simplified: ' + chars.map(function (c) { return c + '→' + MAP[c]; }).join('  ') +
        '。標題字體沒有簡體字，會變成另一種字體。 Heading fonts have no Simplified characters, so these show in a different font. ';
      var btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'mini-btn';
      btn.textContent = '改用繁體 Use Traditional';
      btn.addEventListener('click', function () {
        box.value = convert(box.value);
        box.dispatchEvent(new Event('input', { bubbles: true }));
        box.focus();
      });
      note.appendChild(msg);
      note.appendChild(btn);
    }
    box.addEventListener('input', check);
    check();
  }

  document.querySelectorAll('[data-trad-check]').forEach(function (area) {
    area.querySelectorAll('input[type="text"], input:not([type]), textarea').forEach(watch);
  });
})();
