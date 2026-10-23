# PHP için IndexNow

[![tests](https://github.com/irodevltd/indexnow-php/actions/workflows/tests.yml/badge.svg)](https://github.com/irodevltd/indexnow-php/actions/workflows/tests.yml)
![PHP](https://img.shields.io/badge/php-%3E%3D8.1-777bb4)
![License](https://img.shields.io/badge/license-MIT-blue)

[English](README.md) · **Türkçe**

Bir sayfa eklendiği, değiştiği ya da kaldırıldığı anda Bing, Yandex, Seznam, Naver ve Yep'e doğrudan site
haritanızdan haber verir. Cron, eklenti ya da dış servis gerekmez: birkaç sınıf ve bir JSON dosyası yeterli.

- **Otomatik:** Site haritanızı IndexNow'un en son kabul ettiği listeyle karşılaştırır ve yalnızca farkı gönderir.
- **Kurallara uyar:** Aynı adres en fazla 10 dakikada bir bildirilir. Bildirim reddedilirse bir saat beklenir. Kilit
  dosyası aynı bildirimin iki kez gitmesini önler.
- **Güvenli:** Okunamayan bir site haritası (örneğin yarım yüklenmiş) sayfalarınızı kaldırılmış gibi göstermez.
- **Ziyaretçiyi bekletmez:** Kontrol, sayfa ziyaretçiye gittikten sonra ve en fazla 10 dakikada bir çalışır.

[irodev.net](https://irodev.net) üzerinde çalışan IndexNow entegrasyonunun bağımsız hâlidir.

## Gereksinimler

`json` ve `simplexml` eklentileriyle PHP 8.1 ya da üstü. Varsayılan gönderim için `curl` eklentisi.

## Kurulum

```bash
composer require irodevnet/indexnow
```

## 1. Anahtarınızı oluşturun

IndexNow anahtarı, sitenizin kök dizinindeki bir metin dosyasıyla doğrular. İkisini bir kez oluşturun:

```bash
php vendor/irodevnet/indexnow/examples/create-key.php /belge/kok/dizini
```

ya da kodla:

```php
use Irodev\IndexNow\Key;

$key = Key::generate();                  // 32 onaltılık karakter
Key::writeFile('/belge/kok/dizini', $key);
```

Dosyaya her zaman `https://alan-adiniz/<anahtar>.txt` adresinden ulaşılabilmeli. Anahtar zaten herkese açık olacak
şekilde tasarlanmıştır.

## 2. Adres gönderin

```php
use Irodev\IndexNow\Client;

$client = new Client('ornek.com', $key);
$result = $client->submit(['https://ornek.com/blog/yeni-yazi', 'https://ornek.com/blog']);

if (!$result->accepted()) {
    error_log('IndexNow: ' . $result->reason());
}
```

`Client`, tekrarlanan adresleri ve başka alan adlarına ait adresleri atar (IndexNow bunları reddeder). Alan adı,
adreslerinizdekiyle birebir aynı olmalı: `ornek.com` ile `www.ornek.com` farklı alan adlarıdır.

## 3. Otomatik çalıştırın

Sayfalarınızı oluşturan koddan (ön denetleyici ya da ortak başlık dosyası) çağırın:

```php
use Irodev\IndexNow\{Client, Deferred, JsonLog, Sitemap, Sync};

if (($_SERVER['HTTP_HOST'] ?? '') === 'ornek.com') {       // yalnızca canlı site gönderir
    Deferred::every(600, $storage . '/indexnow.tick', function () use ($storage, $docroot, $key) {
        $sync = new Sync(new Client('ornek.com', $key), $storage . '/indexnow.json',
                         log: new JsonLog($storage . '/indexnow.log'));
        $sync->run(Sitemap::merge(
            Sitemap::fromFile($docroot . '/sitemap.xml'),
            Sitemap::fromFile($docroot . '/sitemap-blog.xml'),
        ));
    });
}
```

`Deferred` her istekte yalnızca bir dosya kontrolü yapar. Çalıştığında PHP-FPM ve LiteSpeed önce hazır sayfayı
ziyaretçiye gönderir; kimse bildirimi beklemez. `$storage` klasörünü web kök dizininin dışında tutun.

Bir editör içerik kaydettiğinde ya da sildiğinde `$sync->run(...)` hemen de çağrılabilir.

## Senkronizasyon nasıl karar verir

| Durum | Ne olur |
| --- | --- |
| Adres yeni ya da `<lastmod>` tarihi değişmiş | Gönderilir |
| Adres site haritasından çıkmış | Bir kez gönderilir; arama motorları yeniden tarayıp kaldırıldığını görür |
| Aynı adres `$minInterval` (600 sn) içinde gönderilmiş | Sonraki çalışmayı bekler (`Outcome::WAITING`) |
| Son bildirim reddedilmiş ya da yanıt alınamamış | `force: true` verilmedikçe `$retryAfter` (3600 sn) boyunca hiçbir şey gönderilmez |
| Site haritası okunamıyor ya da bilinen adresler varken boş | Hiçbir şey gönderilmez (`Outcome::EMPTY_LIST`) |
| Başka bir istek zaten gönderiyor | `Outcome::BUSY` |

`run($entries, force: true, all: true)` bütün adresleri yeniden gönderir; örneğin sitenin tasarımı baştan
değiştiğinde. IndexNow yalnızca değişen adreslerin gönderilmesini istediği için bunu gerçek site geneli
değişikliklere saklayın. `$sync->pending($entries)` o anda nelerin gideceğini gösterir.

Karşılaştırma `<lastmod>` tarihine göre yapıldığı için tarihleri doğru tutun: Bir sayfanın tarihini içeriği
değiştiğinde değiştirin, yalnızca o zaman. Saat içeren tarihler (`2026-10-23T21:30:00+03:00`) aynı gün içinde
sayfanın yeniden gönderilmesini sağlar.

## IndexNow yanıtları

| Kod | Anlamı |
| --- | --- |
| 200 | Alındı |
| 202 | Alındı; anahtar doğrulanıyor |
| 400 | Geçersiz biçim |
| 403 | Anahtar dosyası yok ya da içinde başka bir anahtar var |
| 422 | Adresler başka bir alan adına ait ya da anahtar protokolün biçimine uymuyor |
| 429 | Çok fazla istek |
| 0 | Yanıt yok (DNS, zaman aşımı, bağlantı reddi) |

`$result->reason()` aynı açıklamayı (İngilizce) metin olarak verir.

## Bilmekte fayda var

- Google IndexNow'a katılmıyor; site haritanızı ve Search Console'u okur. Ortak uç noktaya gönderilen tek bir
  bildirim, katılan bütün arama motorlarına ulaşır.
- Her alan adı için bir `Client` kullanın. Hem `www` ile hem de `www` olmadan açılan bir site zaten birinden
  diğerine yönlendirmeli.
- `Transport` bir arayüzdür: Bir proxy ya da zaten kullandığınız bir HTTP istemcisi için kendinizinkini `Client`'a
  verebilirsiniz.
- `JsonLog::recent()` en son bildirimleri, örneğin bir yönetim sayfasında göstermek için verir.

## Testler

```bash
composer install
composer test
```

## Lisans

MIT © [İRODEV LTD](https://irodev.net). İrodev; Türkiye ve Birleşik Krallık'taki işletmeler için web siteleri ve
yazılımlar geliştiren bir stüdyodur. Bunun sitenize kurulmasını isterseniz [bize yazın](https://irodev.net/tr/iletisim).
