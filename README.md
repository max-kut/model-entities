#PHP обертка над `json`-данными

Служит для более управляемой и документированной работы с данными json-полей моделей или ответов различных API

###Установка
`composer require maxkut/model-entities`

###Концепция
Библиотека включает два абстрактных класса `\Entities\Entity` и `\Entities\EntityCollection
`, которые содержат вспомогательные методы для типизации свойств конечных объектов.
Очень важно документировать свойства создаваемых классов. Так Вы не забудете, какие свойства есть у того или иного объекта. В примере ниже задокументированы свойства класса Settings в PhpDoc. Плюс Ваша ide будет вам помогать с подсказками.

###Пример
```PHP
// Как пример сложных json полей модели App\Models\User
namespace App\Models\Entities\User;
use Entities\Entity;
/**
 * Class Settings
 * @property bool $property1
 * @property int $property2
 * @property array $property3
 */
class Settings extends Entity
{
    /**
     * @var bool $strictParams - если true, 
     * то любые свойства, которые не объявлены в $attributes, $casts
 или для него нет акцессора/мутатора 
     * вызовут исключение Entities\Exceptions\NotDefinedPropertyException
     */
    public $strictParams = true;

    protected $attributes = [
        'property1' => null,
        'property2' => null,
        'property3' => null,
    ];

    protected $casts = [
        'property1' => 'bool',
        'property2' => 'int',
        'property3' => 'array',
    ];
}

///////////////////////////////////////////
/// На примере моделей Eloquent ORM 
/// надо создать методы преобразования (акцессор и мутатор) для этого поля

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Entities\User\Settings;

class User extends Model
{
    //...
    
    protected function getSettingsAttribute($value){
        return Settings::make($value);
    }
    
    protected function setSettingsAttribute($value){
        $this->attributes['settings'] =  Settings::make($value)->toJson();
    }
}
```
