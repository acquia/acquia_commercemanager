# Acquia Commerce Manager

[![Build Status](
https://travis-ci.org/acquia/commerce-manager.svg?branch=master
)](https://travis-ci.org/acquia/commerce-manager)

[![Coverage Status](
https://coveralls.io/repos/github/acquia/commerce-manager/badge.svg?branch=8.x-1.x
)](https://coveralls.io/github/acquia/commerce-manager?branch=8.x-1.x)
## Summary
The Acquia Commerce Manager Drupal module enables site builders to use tools
and templates in Drupal to rapidly build commerce experiences. It connects
Drupal sites to Acquia's Commerce Connector Service leveraging eCommerce 
systems as the source of truth for eCommerce data in these experiences.

## Installation
Module can be installed using Composer:
```
composer require acquia/commerce-manager
```

If you don't want to use Composer, you can install Acquia Commerce Manager
the traditional way by downloading a tarball from 
Acquia Commerce Manager's [GitHub releases page](
https://github.com/acquia/commerce-manager/releases
). Please note that this tarball doesn't include all necessary dependencies
and Composer is recommended way of installation. When installing using Composer, 
make sure that you have also Drupal composer in your composer.json.

From version 1.5.0, we require at least PHP 7.1.

## Configuration
For module installation and configuration please follow [Acquia Knowledgebase](
https://docs.acquia.com/commerce/install/modules
). 

### Users

In Acquia Commerce Manager, there are two types of user accounts.  Regular
drupal users and external users that log in against an ecommerce backend. 
By default it will be drupal users, but you can enable external users by going
to /admin/commerce/config/commerce-users and enabling "Use E-Comm Sessions".
After that setting is enabled, it will bring up three additional fields to
configure where your login form, registration form, and logout page are
located.

The login form will sign a user in using the ecommerce backend to authenticate
the username and password, and if authenticated, return an access token that
will be sent along with all user requests.

The registration form will create the account in the ecommerce backend and
immediately log the user in.

The logout page handles forgetting the users access token.

## Locks

Acquia Commerce Manager uses Drupal locks to avoid data duplication and issues 
around data creating through APIs. To avoid issues with use of memcache for 
locks, persistent locks are used. Check [here](
https://www.drupal.org/project/memcache/issues/3020060) for more details on 
issues with memcache. 

## Contributing
Issues and contributions are welcomed on our [GitHub](
https://github.com/acquia/commerce-manager
).
Please check our [contribution guide](
https://github.com/acquia/commerce-manager/blob/master/CONTRIBUTING.md
) first.

## Copyright and license

Acquia Commerce Manager Drupal module

Copyright &copy; 2018 Acquia Inc.

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License along
with this program; if not, write to the Free Software Foundation, Inc.,
51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
