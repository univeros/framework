### Greet someone

```php
$greeter = new SampleGreeter();
echo $greeter->greet('world');
```

---

### Bring your own greeting

```php
final class Shouter implements GreeterInterface { /* ... */ }
```
