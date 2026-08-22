# Client\Topup Feature

Owns the public Blade delivery layer for the game topup flow:

- homepage and catalog controllers;
- checkout and guest/account order controllers;
- checkout and lookup form requests;
- public and account order routes.

Business rules belong to `App\Features\Topup`; controllers in this module only validate, delegate and return responses.
