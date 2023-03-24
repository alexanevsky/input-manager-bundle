# Input Manager

This library allows you to map incoming data (for example, from JSON) into an object, modify it, validate it, and map it to your model or Doctrine entity. This allows you to treat the data as an object and still prevent invalid property values for your model or Doctrine entity.

The library consists of three components:

* Deserializer
* Validator
* Mapper of input data to the model

Let's analyze each of them step by step.

## Table of Contents

1. [First Step](#first-step)
2. [Deserializer](#deserializer)
    * [Basic Example](#basic-example)
    * [Type Conversion](#type-conversion)
    * [Objects Deserializing](#objects-deserializing)
    * [Nested Inputs Deserializing](#nested-inputs-deserializing)
    * [Input Collection Deserializing](#input-collection-deserializing)
    * [an Entity by Identifier Deserializing](#an-entity-by-identifier-deserializing)
    * [an Array of Entities by Identifier Deserializing](#an-array-of-entities-by-identifier-deserializing)
    * [Input Modifier](#input-modifier)
3. [Validator](#validator)
    * [Constraints Validator](#constraints-validator)
    * [Extended Validator](#extended-validator)
    * [Extended Validator Payload](#extended-validator-payload)
4. [Input to Object (Model) Mapper](#input-to-object-model-mapper)

## First Step

Add `InputManager` to the constructor of controller or service:

```php
use Alexanevsky\InputManagerBundle\InputManager;

public function __construct(
    private InputManager $inputManager
) {}
```

## Deserializer

### Basic Example

Let's imagine that we have some model:

```php
class User
{
    private string $firstName;

    private string $lastName;

    public function setFirstName(string $firstName): void
    {
        $this->firstName = $firstName;
    }

    public function setLastName(string $lastName): void
    {
        $this->lastName = $lastName;
    }
}
```

To map the data from the request to this model, we will use an intermediate object that implements `InputInterface` so that we can check and modify the incoming data if we need it:

```php
use Alexanevsky\InputManagerBundle\Input\InputInterface;

class UserInput implements InputInterface
{
    public string $firstName;

    public string $lastName;
}
```

We can describe properties as public. We can also make them private and use setters and getters:

```php
use Alexanevsky\InputManagerBundle\Input\InputInterface;

class UserInput implements InputInterface
{
    private string $firstName;

    private string $lastName;

    public function getFirstName(): string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): void
    {
        $this->firstName = $firstName;
    }

    public function getLastName(): string
    {
        return $this->lastName;
    }

    public function setLastName(string $lastName): void
    {
        $this->lastName = $lastName;
    }
}
```

You can use any approach you want. We will use public properties in this documentation.

*Note: If a public property has a getter (or setter), it will take precedence, i.e. the value of the getter will be taken (passed to the setter) rather than taken from the property (rather than assigned to the property). You can see more about how getters and setters are used in the library [alexanevsky/getter-setter-accessor](https://github.com/alexanevsky/getter-setter-accessor).*

So, the first step we need to do is deserialize our request to the Input object.

```php
$json = '{"firstName": "John", "last_name": "Doe"}';
$input = new UserInput();
$this->deserializeInput($json, $input);
```

Or we can create an object during deserialization (either approach can be used):

```php
$json = '{"firstName": "John", "last_name": "Doe"}';
$input = $this->deserializeInput($json, UserInput::class);
```

Please note that the deserializer can work with keys in both camel and snake cakes.

As a result, our `$input` will be like this:

```php
echo $input->firstName; // John
echo $input->lastName; // Doe
```

### Type Conversion

The deserializer can convert simple data types.

Imagine that our Input expects the following data:

```php
class UserInput implements InputInterface
{
    public bool $anyBoolValue;

    public bool $anyAnotherBoolValue;

    public int $anyIntValue;
}
```

And we will pass data of a slightly wrong type:

```php
$json = '{"any_bool_value": 1, "any_another_bool_value": '', "anyIntValue": "123"}';
$input = $this->deserializeInput($json, UserInput::class);

echo $input->anyBoolValue; // true (bool)
echo $input->anyAnotherBoolValue; // false (bool)
echo $input->anyIntValue; // 123 (int)
```

As we can see, the deserializer coped with this task and converted the types to the required ones.

### Objects Deserializing

Imagine that we have a model that takes some object as its property.

```php
class Address
{
    private string $city;

    private string $street;

    private string $building;

    // Setters and getters of properties...
}

class User
{
    private Address $address;

    // Setters and getters of properties...
}
```

In our Input class, we must use it:

```php
class UserInput implements InputInterface
{
    private Address $address;
}
```

As a result, our Input will be successfully deserialized from this JSON:

```php
$json = '{"address": {"city": "string", "street": "string", "building": "string" }}';
```

### Nested Inputs Deserializing

Your Input can accept a nested Input, which will also be deserialized and converted into the appropriate model object in the future.

Imagine that we have a model that accepts another model as its property.

```php
class Category
{
    private string $name;

    // Getters and setters of properties...
}

class Article
{
    private string $title;

    private Category $category;

    // Getters and setters of properties...
}
```

These models will correspond to the following Input classes:

```php
class CategoryInput implements InputInterface
{
    public string $name;
}

class ArticleInput implements InputInterface
{
    public string $title;

    public CategoryInput $category;
}
```

As a result, our `ArticleInput` will be successfully deserialized from this JSON:

```php
$json = '{"title": "Lorem Ipsum", "category": {"name": "Dolor"}}';
```

### Input Collection Deserializing

If our model has a property that does not contain one other model, but an array of models, we can create a collection input class that implements `InputCollectionInterface` (even easier - extends `AbstractInputCollection`), and define in it which Input we need to use:

```php
use Alexanevsky\InputManagerBundle\Input\AbstractInputCollection;
use Alexanevsky\InputManagerBundle\Input\InputCollectionInterface;

class CategoryInput implements InputInterface
{
    public string $name;
}

class CategoryInputCollection extends AbstractInputCollection
{
    public function getClass(): string
    {
        return CategoryInput::class;
    }
}

class ArticleInput implements InputInterface
{
    public string $title;

    public CategoryInputCollection $categories;
}
```

As a result, our `ArticleInput` will be successfully deserialized from this JSON:

```php
$json = '{"title": "Lorem Ipsum", "categories": [{"name": "Dolor"}, {"name": "Sit"}]}';
```

### an Entity by Identifier Deserializing

Imagine that in the request data we only have the identifier of some entity, but we need to deserialize it into the entity itself:

Our entities look like this:

```php
class Category
{
    private string $id;

    // Getters and setters of properties...
}

class Article
{
    private Category $category;

    // Getters and setters of properties...
}
```

To get the `Category` object by the passed `id`, add the `EntityFromId` attribute to our Input class, specifying which class we expect as the first parameter:

```php
use Alexanevsky\InputManagerBundle\Input\Attribute\EntityFromId;

class ArticleInput implements InputInterface
{
    #[EntityFromId(Category::class)]
    public Category $category;
}
```

As a result, our `ArticleInput` will be successfully deserialized from this JSON:

```php
$json = '{"category_id": 1}';
```

We can also do without the suffix:

```php
$json = '{"category": 1}';
```

If the identity property of our `Category` is different than `id`, we need to pass the name of the identity property as the second parameter to `EntityFromId`:

```php
class Category
{
    private string $code;

    // Getters and setters of properties...
}

class Article
{
    private Category $category;

    // Getters and setters of properties...
}



class ArticleInput implements InputInterface
{
    #[EntityFromId(Category::class, 'code')]
    public Category $category;
}
```

As a result, our `ArticleInput` will be successfully deserialized from this JSON:

```php
$json = '{"category_code": "cat"}';
```

We can also do without the suffix:

```php
$json = '{"category": "cat"}';
```

We can also specify with the third parameter `EntityFromId` what suffix we expect in the input data:

```php
class ArticleInput implements InputInterface
{
    #[EntityFromId(Category::class, 'code', 'identifier')]
    public Category $category;
}
```

As a result, our `ArticleInput` will be successfully deserialized from this JSON:

```php
$json = '{"category_identifier": "cat"}';
```

We can also avoid the suffix, as in the two examples above.

If we don't want any suffix at all, we must pass false as the third parameter of `EntityFromId`:

```php
class ArticleInput implements InputInterface
{
    #[EntityFromId(Category::class, 'code', false)]
    public Category $category;
}
```

### an Array of Entities by Identifier Deserializing

The `EntityFromId` attribute described above can also be used for models.

```php
class Category
{
    private string $id;

    // Getters and setters of properties...
}

class Article
{
    private Collection $categories;

    // Getters and setters of properties...
}

class ArticleInput implements InputInterface
{
    #[EntityFromId(Category::class)]
    public array $categories;
}
```

A slight difference is that if the second parameter (suffix) is not specified, the deserializer will expect it with `s` at the end. That is, our `ArticleInput` will be successfully deserialized from this JSON:

```php
$json = '{"categories_ids": [1, 2]}';
```

We can also do without the suffix:

```php
$json = '{"categories": [1, 2]}';
```

### Input Modifier

We can implement our Input from `InputModifiableInterface` instead of `InputInterface`. This will allow us to add a `modify()` method that will be called immediately after deserialization. This will allow us to change some of our Input data.

```php
use Alexanevsky\InputManagerBundle\Input\InputModifiableInterface;

class ArticleInput implements InputModifiableInterface
{
    public string $title;

    public \DateTime $createdAt;

    public function modify(): void
    {
        $this->title .= ' from ' . $createdAt->format('m/d/Y');
    }
}
```

So, given the following as request:

```php
$json = '{"title": "Lorem Ipsum", "createdAt": "2023-01-01 12:00:00"}';
```

Our Input will be with the following data after derealization:

```php
echo $input->title; // 'Lorem Ipsum from 01/01/2023'
```

## Validator

### Constraints Validator

The easiest way to validate is to use [Symfony Constraints](https://symfony.com/doc/current/validation.html#constraints).

Let's set the constraints attributes on our Input class:

```php
use Symfony\Component\Validator\Constraints as Assert;

class UserInput implements InputInterface
{
    #[Assert\NotBlank]
    public string $name;

    #[Assert\NotBlank]
    #[Assert\Email]
    public string $email;
}
```

After we have deserialized our Input, let's validate it:

```php
use Symfony\Component\Translation\TranslatableMessage;

/** @var TranslatableMessage[] $errors */
$errors = $this->inputManager->validate($input);
```

As a result, we will get an associative array of errors, in which the key is the name of the property in which the error occurred, and the value is `TranslatableMessage` with the error message.

### Extended Validator

We can create our own extended validator that will implements `InputValidatorInterface` (even easier - extends `AbstractInputValidator`). In it, we specify the `validate()` method, which will perform the necessary checks and return an associative array of errors, in which the key is the name of the property in which the error occurred, and the value is `TranslatableMessage` with the error message. If it returns an empty array, it means that the validation was successful.

```php
use App\Component\InputManager\InputValidator\AbstractInputValidator;

class UserInput implements InputInterface
{
    public string $name;

    public string $email;
}

class UserInputValidator extends AbstractInputValidator
{
    public function validate(): array
    {
        $errors = [];

        if (!$this->getInput()->name) {
            $errors['name'] = new TranslatableMessage('Name is empty!');
        }

        if (!$this->getInput()->email) {
            $errors['email'] = new TranslatableMessage('Email is empty!');
        } elseif (filter_var($this->getInput()->email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = new TranslatableMessage('Email is incorrect!');
        }

        return $errors = [];
    }
}
```

To use this validator, specify its class name as the second parameter to the validation method:

```php
$errors = $this->inputManager->validate($input, UserInputValidator::class);
```

*Note that the extended validator will only be called if there were no errors defined by constraints.*

We can choose another way: in our extended validator, create validation methods for each property by prepend `validate` to the property name in the method name. We will have to return `TranslatableMessage` if there is an error, or null if there is no error.

```php
class UserInputValidator extends AbstractInputValidator
{
    public function validateName(): ?TranslatableMessage
    {
        if (!$this->getInput()->name) {
            return new TranslatableMessage('Name is empty!');
        }

        return null;
    }

    public function validateEmail(): ?TranslatableMessage
    {
        if (!$this->getInput()->email) {
            return new TranslatableMessage('Email is empty!');
        } elseif (filter_var($this->getInput()->email, FILTER_VALIDATE_EMAIL)) {
            return new TranslatableMessage('Email is incorrect!');
        }

        return null;
    }
}
```

### Extended Validator Payload

We can also pass some payload to our validator to use in validation. To do this, we will add a public property and add the `SetFromPayload` attribute to it, which will tell the validator that the value of the property should be received from the payload. We can also set the boolean `requred` parameter. If `required` is `true` and there is no data in the payload, we will have an exception. For example, we can pass to the validator the entity of our user for whom the current Input is being processed.

```php
use Alexanevsky\InputManagerBundle\InputValidator\Attribute\SetFromPayload;

class UserInputValidator extends AbstractInputValidator
{
    #[SetFromPayload(true)]
    public User $user;

    public function __construct(
        private UserRepository $usersRepository
    ) {
    }

    public function validateEmail(): ?TranslatableMessage
    {
        $foundedUser = $this->usersRepository->findOneBy(['email' => $this->getInput()->email]);

        return !$foundedUser || $this->user === $foundedUser
            ? null
            : new TranslatableMessage('User with this email already exists!');
    }
}
```

To pass the payload to the extended validator, pass it as the third parameter to the validator method:

```php
$errors = $this->inputManager->validate($input, UserInputValidator::class, ['user' => $user]);
```

## Input to Object (Model) Mapper

Finally, having completed the deserialization and validation, we need to map the data from our Input to our Model. We will do this by simple method:

```php
$this->$inputManager->mapInputToObject($input, $user);
```

And all our deserialized valid data from Input will be set to the user by public properties and setters.

Good luck!
