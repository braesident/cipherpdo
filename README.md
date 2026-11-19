# CipherPDO

`braesident/cipherpdo` erweitert PDO um feldweise Ver-/Entschlüsselung und die Wiederverwendung benannter SQL-Parameter. CipherPDO ist der eigenständige Nachfolger von JPdo. Die SQL-Erzeugung gehört zu `braesident/dialectsql`; Migrationen gehören zu `braesident/dbmigration`. Beide Pakete sind optional.

## Installation

Nach Veröffentlichung: `composer require braesident/cipherpdo:^1.0`.
Für die lokale Entwicklung kann das Projekt als Composer-Repository vom Typ `path` eingebunden werden. Ein Git-Tag beziehungsweise eine passende lokale Versionsangabe bestimmt die installierbare Version; dieses Projekt ist noch nicht veröffentlicht.

## Verbindung und Verschlüsselung

```php
use Braesident\Blowfish\Blowfish;
use Braesident\CipherPDO\CipherPDO;

// Dieselbe bereits konfigurierte Schlüsselinstanz wie bisher verwenden.
$db = CipherPDO::mysql($host, $database, $user, $password, $cipher);
$stmt = $db->prepare('UPDATE contact SET cMail = :mail WHERE kContact = :id');
$stmt->encrypt(['mail'])->execute(['mail' => $email, 'id' => $id]);

$stmt = $db->prepare('SELECT cMail FROM contact WHERE kContact = :id');
$stmt->execute(['id' => $id]);
$row = $stmt->fetchObjectDecrypted(['cMail']);
```

`encrypt()` benennt die zu verschlüsselnden Parameter. Die `fetch*Decrypted()`-Methoden benennen Ergebnisspalten. `mysql()` und `sqlsrv()` bleiben Verbindungsfabriken dieses Pakets. Ein beliebiger PDO-DSN kann über `CipherPDO::fromDsn($dsn, blowfish: $cipher)` geöffnet werden. Der bisherige Konstruktor bleibt ebenfalls verfügbar.

CipherPDO verändert weder vorhandene Schlüssel noch gespeicherte Werte automatisch. Das Speicherformat wird weiterhin durch `braesident/blowfish` bestimmt; dessen Mindestversion ist 1.0.3. Der für den DSN benötigte PDO-Treiber muss installiert sein.

## Unabhängigkeit und Migration

CipherPDO kann ohne DialectSql und DbMigration verwendet werden. Es erbt von `PDO` und kann überall übergeben werden, wo eine PDO-Verbindung erwartet wird. Ein Projekt, das ausdrücklich `JPdo` typisiert, muss seinen Import beziehungsweise Typ anpassen; CipherPDO behauptet über Composer weder `replace` noch eine automatische Typgleichheit mit JPdo.

Für den Umstieg:

- Composer-Paket: `braesident/jpdo` → `braesident/cipherpdo`.
- Klasse: `Braesident\JPdo\JPdo` → `Braesident\CipherPDO\CipherPDO`.
- Statement: `Braesident\JPdo\JPdoStatement` → `Braesident\CipherPDO\CipherPDOStatement`.
- Schlüssel, Datenbankspalten und Datenbestände beibehalten.

Die bisherigen JPdo-Symbole sind ab JPdo 1.1.0 deprecated und zur Entfernung in JPdo 2.0.0 vorgesehen. Das sind Versionsgrenzen, keine bereits veröffentlichten Releases oder Kalendertermine.

## Tests

Nach `composer install`: `composer test`. Die Tests verwenden ausschließlich SQLite im Speicher und synthetische Schlüssel. Für die Prüfung mit bereits installierten Abhängigkeiten kann `CIPHERPDO_DEPENDENCY_AUTOLOAD` auf deren `vendor/autoload.php` zeigen.
