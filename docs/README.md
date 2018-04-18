# ACM Drupal Modules

## Show available routes

```
./bin/show-routes.sh
```

## Users

In ACM, there are two types of user accounts.  Regular drupal users and
external users that log in against an ecommerce backend.  By default it will be
drupal users, but you can enable external users by going to
/admin/commerce/config/commerce-users and enabling "Use E-Comm Sessions".
After that setting is enabled, it will bring up three additional fields to
configure where your login form, registration form, and logout page are located.

The login form will sign a user in using the ecommerce backend to authenticate
the username and password, and if authenticated, return an access token that
will be sent along with all user requests.

The registration form will create the account in the ecommerce backend and
immediately log the user in.

The logout page handles forgetting the users access token.
