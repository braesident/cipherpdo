<?php

declare(strict_types=1);

$dependencyAutoload = getenv('CIPHERPDO_DEPENDENCY_AUTOLOAD') ?: dirname(__DIR__).'/vendor/autoload.php';
if ( ! is_file($dependencyAutoload)) {
  throw new RuntimeException('Zuerst composer install ausführen oder CIPHERPDO_DEPENDENCY_AUTOLOAD setzen.');
}
require $dependencyAutoload;
spl_autoload_register(static function (string $class): void {
  $prefix = 'Braesident\\CipherPDO\\';
  if (str_starts_with($class, $prefix)) {
    require dirname(__DIR__).'/src/'.substr($class, strlen($prefix)).'.php';
  }
}, true, true);

use Braesident\Blowfish\Blowfish;
use Braesident\CipherPDO\CipherPDO;
use Braesident\CipherPDO\CipherPDOStatement;

$checks = 0;
function check(bool $condition, string $message): void
{
  global $checks;
  if ( ! $condition) {
    throw new RuntimeException($message);
  }
  ++$checks;
}

final class ContactFixture
{
  public int $id;
  public ?string $email;
}

$cipher = new Blowfish('synthetic-cipherpdo-test-key');
$db = CipherPDO::fromDsn('sqlite::memory:', blowfish: $cipher);
check($db instanceof PDO, 'CipherPDO muss als PDO nutzbar bleiben.');
$db->exec('CREATE TABLE contacts (id INTEGER PRIMARY KEY, email TEXT NULL, copy TEXT NULL)');
$insert = $db->prepare('INSERT INTO contacts (id, email, copy) VALUES (:id, :email, :email)');
check($insert instanceof CipherPDOStatement, 'prepare() muss CipherPDOStatement liefern.');
$plain = 'Änne & <Kontakt> "Test"';
$insert->encrypt(['email'])->execute(['id' => 1, 'email' => $plain]);
$stored = $db->query('SELECT * FROM contacts WHERE id = 1')->fetch(PDO::FETCH_OBJ);
check($stored->email !== $plain, 'Klartext darf nicht gespeichert werden.');
check(trim($cipher->Decrypt($stored->email)) === $plain, 'Ciphertext muss kompatibel lesbar bleiben.');
check(trim($cipher->Decrypt($stored->copy)) === $plain, 'Mehrfach verwendete verschlüsselte Parameter müssen erweitert werden.');

$select = $db->prepare('SELECT id, email FROM contacts WHERE id = :id');
$select->execute(['id' => 1]);
check($select->fetchObjectDecrypted(['email'])->email === $plain, 'Einzelobjekt muss entschlüsselt werden.');
$select->execute([':id' => 1]);
check($select->fetchDecrypted(['email'])->email === $plain, 'fetchDecrypted muss kompatibel bleiben.');

$insert = $db->prepare('INSERT INTO contacts (id, email) VALUES (:id, :email)');
foreach ([2 => null, 3 => ''] as $id => $value) {
  $insert->encrypt(['email'])->execute(['id' => $id, 'email' => $value]);
}
$select = $db->prepare('SELECT id, email FROM contacts ORDER BY id');
$select->execute();
$rows = $select->fetchAllDecrypted(['email'], PDO::FETCH_OBJ);
check($rows[0]->email === $plain && null === $rows[1]->email && '' === $rows[2]->email, 'Objektlisten müssen Klartext, null und Leerstring erhalten.');
$select->execute();
$rows = $select->fetchAllDecrypted(['email'], PDO::FETCH_ASSOC);
check($rows[0]['email'] === $plain && null === $rows[1]['email'], 'Assoziative Listen müssen entschlüsselt werden.');
$select->execute();
$rows = $select->fetchAllDecrypted(['email'], PDO::FETCH_CLASS, ContactFixture::class);
check($rows[0] instanceof ContactFixture && $rows[0]->email === $plain, 'Modellhydratisierung muss erhalten bleiben.');

$duplicate = $db->prepare("SELECT :value + :value + :value_0 AS total, ':ignored' AS literal /* :ignored */");
$duplicate->execute(['value' => 4, 'value_0' => 2]);
$result = $duplicate->fetch(PDO::FETCH_OBJ);
check(10 === (int) $result->total && ':ignored' === $result->literal, 'Platzhalterkollisionen, Literale und Kommentare müssen korrekt behandelt werden.');
$duplicate->execute([':value' => 5, ':value_0' => 3]);
check(13 === (int) $duplicate->fetchColumn(), 'Parameter mit Doppelpunkt müssen ebenfalls erweitert werden.');

$plainDb = CipherPDO::fromDsn('sqlite::memory:');
check(7 === (int) $plainDb->query('SELECT 7')->fetchColumn(), 'Eine Verbindung ohne Cipher muss weiterhin funktionieren.');
echo "CipherPDO: {$checks} Prüfungen erfolgreich.\n";
