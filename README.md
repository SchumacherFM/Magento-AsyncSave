Magento AsyncSave
=================

Magento implementation of http://au1.php.net/manual/en/mysqli.query.php to use MYSQLI_ASYNC for inserts and updates.

Fire and forget.

This fails because Magento uses too many connections and one async query can
only use one connection. IMHO setting the max connection variable to a higher
value cannot be a solution. The guy here http://sysmagazine.com/posts/155377/ stuck in the same situation ...

Real DML async queries with one connection are not possible :-(

API
---

Please only instantiate this resource via singleton.

### Available methods

`$this save(Mage_Core_Model_Abstract $object, array $_fieldsForUpdate = null)`

With its _beforeSave and _afterSave methods if you want extend the Async resource class.

`$this delete(Mage_Core_Model_Abstract $object)`

With its _beforeDelete and _afterDelete methods if you want extend the Async resource class.

For both methods `save()` and `delete()` the provided `$object` must have a valid resource object.

`$this setSerializableFields(array $serializableFields)`

`raw_query($sql, array $bind = null)`

`null|bool|mysqli_result getLastAsyncResult()`

Examples
--------

```php
$collection = Mage::getModel('catalog/product')->getCollection();
foreach($collection as $product){
    $product->setName(...)->setPrice(...);
    Mage::getResourceSingleton('schumacherfm_asyncsave/async')->save($product);
}
```

```php
    $sql = $select->insertFromSelect($this->getFlatTableName($storeId), $fieldList);
    Mage::getResourceSingleton('schumacherfm_asyncsave/async')->raw_query($sql, $bind);
```

Disadvantage / Risks
--------------------

This method provides no prepared statements. You are at the risk of [SQL injections](https://www.owasp.org/index.php/SQL_Injection).

Compatibility
-------------

- Magento1 >= 1.6
- php >= 5.3.0 with mysqli and mysqlnd

There exists the possibility that this extension may work with pre-1.5 Magento versions.

Support / Contribution
----------------------

Report a bug using the issue tracker or send us a pull request.

Instead of forking I can add you as a Collaborator IF you really intend to develop on this module. Just ask :-)

We work with: [A successful Git branching model](http://nvie.com/posts/a-successful-git-branching-model/) and [Semantic Versioning 2.0.0](http://semver.org/)

Licence OSL-3
-------------

Copyright (c) 2013 Cyrill (at) Schumacher dot fm

[Open Software License (OSL 3.0)](http://opensource.org/licenses/osl-3.0.php)

Author
------

- [Cyrill Schumacher](https://github.com/SchumacherFM)
- [My pgp public key](http://www.schumacher.fm/cyrill.asc)
- [keybase.io](https://keybase.io/cyrill)

Made in Sydney, Australia :-)

If you consider a donation please contribute to: [http://www.seashepherd.org/](http://www.seashepherd.org/)
