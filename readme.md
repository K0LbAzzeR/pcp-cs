#ВНИМАНИЕ!!!
Проект больше не поддерживается и не обновляется, т.к. был создан новый аналогичный усовершенствованный проект:

[github.com/Armature](https://github.com/Armature)

Текущий репозиторий возможно будет вскоре удален. Все пожелания и предложения перенесены в Арматуру.


## PCP-CS — PHP code protect
PHP code protect - железная привязка скриптов к определенным ограничениям на клиент-серверном принципе.

### Требования для сервера

- PHP 5.3+
- MySQL 5.1+


### Требования для клиента

- PHP 5.3+
- PHP скрипт, в который внедряется клиент.

### Термины

- **Клиент** - PHP код, который встраивается в готовый php скрипт для создания и регулирования ограничений.
- **Сервер** - PHP код, который находится у владельца готового php скрипта на хостинге с круглосуточным доступом. Клиент делает запросы на сервер, для получения информации о лицензиях.
- **Ключ активации** - уникальный набор символов латинского алфавита, через который снимаются ограничения для использования готового PHP скрипта, путем запроса локального ключа с сервера.
Локальный ключ - набор зашифрованой информации, которая получается в процессе работы с сервера, используя Лицензионный ключ.

### Ссылки
- [Статья с описанием принципа работы](http://pafnuty.name/statyi/157-pcp-cs.html)
- [Написать автору](https://mofsy.ru/contacts/email)