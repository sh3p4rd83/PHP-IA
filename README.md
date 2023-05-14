# Web 4 - TP2
## Authors
*Romain ROUX*<br>
*Maxime Lefebvre-O*

## Setup

```
docker run --rm \
-u "$(id -u):$(id -g)" \
-v "$(pwd):/var/www/html" \
-w /var/www/html \
laravelsail/php82-composer:latest \
composer install --ignore-platform-reqs
```

```sail artisan migrate:fresh```<br>
```sail artisan db:seed```<br>
```sail artisan tinker```<br>
```$user = App\Models\User::find(1)```<br>
```$user->createToken('api')```

Je pense que je ne vous apprend rien de nouveau avec les commandes, mais ca me semblait pertinent de les mettre :)


## IA

Je suis assez fier de l'IA, il n'y a plus de problemes qui sortent. Pour ce qui est de sa force, après mes tests, je perd environ 4 parties sur 5.
