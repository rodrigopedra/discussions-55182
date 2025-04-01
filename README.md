# Test Project

See https://github.com/laravel/framework/discussions/55182

## Installation

```bash
git clone https://github.com/rodrigopedra/discussions-55182.git
cd discussions-55182
composer run post-root-package-install
composer install
composer run post-create-project-cmd
php artisan db:seed
php artisan serve
```

Access: http://127.0.0.1:8000/api/test

Test existing tenant with header:

```console
$ curl -X GET --location "http://127.0.0.1:8000/api/test" -H "Accept: application/json" -H "X-Tenant: foo"
{"host":["127.0.0.1:8000"],"user-agent":["curl\/8.12.1"],"accept":["application\/json"],"x-tenant":["foo"]}
```

Test missing tenant with header:

```console
$ curl -X GET --location "http://127.0.0.1:8000/api/test" -H "Accept: application/json" -H "X-Tenant: bar"
{
    "message": "Tenant not found"
}
```

Test without header:

```console
$ curl -X GET --location "http://127.0.0.1:8000/api/test" -H "Accept: application/json" 
{
    "message": "Tenant identifier required (subdomain or X-Tenant header)"
}
```
