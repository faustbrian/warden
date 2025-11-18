<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Default Primary Key Type
    |--------------------------------------------------------------------------
    |
    | This option controls the type of primary key used throughout Warden's
    | database tables. The default "id" type uses auto-incrementing integers,
    | which is suitable for most applications. Alternatively, you may choose
    | "ulid" for sortable, time-ordered unique identifiers, or "uuid" for
    | universally unique identifiers that are globally unique.
    |
    | The primary key type you select here will be applied consistently across
    | all Warden tables including roles, permissions, and assignment tables.
    |
    | Supported: "id", "ulid", "uuid"
    |
    */

    'primary_key_type' => env('WARDEN_PRIMARY_KEY_TYPE', 'id'),

    /*
    |--------------------------------------------------------------------------
    | User Model
    |--------------------------------------------------------------------------
    |
    | This option defines the fully-qualified class name of the User model
    | that will be used throughout Warden for role and permission assignments.
    | This model should exist in your application and typically represents
    | your authenticated users. The default is Laravel's standard User model.
    |
    | If you have customised your User model or placed it in a different
    | namespace, you may specify the correct class name here or via the
    | WARDEN_USER_MODEL environment variable.
    |
    */

    'user_model' => env('WARDEN_USER_MODEL', 'App\Models\User'),

    /*
    |--------------------------------------------------------------------------
    | Default Guard
    |--------------------------------------------------------------------------
    |
    | This option defines the default authentication guard used for permissions
    | when no guard is explicitly specified via Warden::guard(). This should
    | match one of your authentication guards defined in config/auth.php.
    |
    | When you have multiple guards (web, api, rpc), this determines which
    | guard's permissions are used by default. You can override this on a
    | per-operation basis using Warden::guard('api')->allow(...).
    |
    */

    'guard' => env('WARDEN_GUARD', 'web'),

    /*
    |--------------------------------------------------------------------------
    | Polymorphic Relationship Types
    |--------------------------------------------------------------------------
    |
    | These options control how polymorphic relationships are stored throughout
    | Warden's permission system. The "morph" type stores the fully-qualified
    | class name in the database, while custom aliases may be used to store
    | shorter, more readable identifiers in your polymorphic type columns.
    |
    | Actors represent the entities performing actions (typically users).
    | Boundaries represent the limiting layer within which permissions apply (teams, organizations).
    | Subjects represent the resources being acted upon (posts, comments).
    |
    | Each relationship type may be configured independently to suit your
    | application's needs. Using custom aliases can reduce database size
    | and make your data more readable when inspected directly.
    |
    */

    'actor_morph_type' => env('WARDEN_ACTOR_MORPH_TYPE', 'morph'),

    'boundary_morph_type' => env('WARDEN_BOUNDARY_MORPH_TYPE', 'morph'),

    'subject_morph_type' => env('WARDEN_SUBJECT_MORPH_TYPE', 'morph'),

    /*
    |--------------------------------------------------------------------------
    | Polymorphic Key Mapping
    |--------------------------------------------------------------------------
    |
    | This option allows you to specify which column should be used as the
    | foreign key for each model in polymorphic relationships. This is
    | particularly useful when different models in your application use
    | different primary key column names, which is common in legacy systems
    | or when using ULIDs and UUIDs alongside traditional auto-incrementing
    | integer keys.
    |
    | For example, if your User model uses 'id' but your Organization model
    | uses 'ulid', you can map each model to its appropriate key column here.
    | Warden will then use the correct column when storing foreign keys.
    |
    | Note: You may only configure either 'morphKeyMap' or 'enforceMorphKeyMap',
    | not both. Choose the non-enforced variant if you want to allow models
    | without explicit mappings to use their default primary key.
    |
    */

    'morphKeyMap' => [
        // App\Models\User::class => 'id',
        // App\Models\Organization::class => 'id',
    ],

    /*
    |--------------------------------------------------------------------------
    | Enforced Polymorphic Key Mapping
    |--------------------------------------------------------------------------
    |
    | This option works identically to 'morphKeyMap' above, but enables strict
    | enforcement of your key mappings. When configured, any model referenced
    | in a polymorphic relationship without an explicit mapping defined here
    | will throw a MorphKeyViolationException.
    |
    | This enforcement is useful in production environments where you want to
    | ensure all models participating in polymorphic relationships have been
    | explicitly configured, preventing potential bugs from unmapped models.
    |
    | Note: Only configure either 'morphKeyMap' or 'enforceMorphKeyMap'. Using
    | both simultaneously is not supported. Choose this enforced variant when
    | you want strict type safety for your polymorphic relationships.
    |
    */

    'enforceMorphKeyMap' => [
        // App\Models\User::class => 'id',
        // App\Models\Organization::class => 'id',
    ],

    /*
    |--------------------------------------------------------------------------
    | Migration Configuration
    |--------------------------------------------------------------------------
    |
    | These options configure Warden's migration tools for importing data from
    | other popular Laravel permission packages. If you're migrating from an
    | existing permissions system, you may specify the table names and settings
    | used by your current package here.
    |
    | Warden provides migration support for both Spatie's Laravel Permission
    | package and Bouncer. The migrator will read from your existing tables
    | and import roles, permissions, and assignments into Warden's schema.
    |
    | Each migrator can be configured independently with the appropriate table
    | names from your existing installation. If your tables use non-standard
    | names, you may specify them here or via environment variables.
    |
    */

    'migrators' => [

        /*
        |----------------------------------------------------------------------
        | Bouncer Migration Settings
        |----------------------------------------------------------------------
        |
        | Configuration for migrating from Bouncer to Warden. Specify the table
        | names used by your Bouncer installation. The entity_type option should
        | match the model type used in Bouncer's polymorphic relationships.
        |
        */

        'bouncer' => [
            'tables' => [
                'abilities' => env('WARDEN_BOUNCER_ABILITIES_TABLE', 'abilities'),
                'roles' => env('WARDEN_BOUNCER_ROLES_TABLE', 'roles'),
                'assigned_roles' => env('WARDEN_BOUNCER_ASSIGNED_ROLES_TABLE', 'assigned_roles'),
                'permissions' => env('WARDEN_BOUNCER_PERMISSIONS_TABLE', 'permissions'),
            ],
            'entity_type' => env('WARDEN_BOUNCER_ENTITY_TYPE'),
        ],

        /*
        |----------------------------------------------------------------------
        | Spatie Permission Migration Settings
        |----------------------------------------------------------------------
        |
        | Configuration for migrating from Spatie's Laravel Permission package
        | to Warden. Specify the table names used by your Spatie installation.
        | The model_type represents the morph type used for users or other
        | models that have permissions assigned directly.
        |
        */

        'spatie' => [
            'tables' => [
                'permissions' => env('WARDEN_SPATIE_PERMISSIONS_TABLE', 'permissions'),
                'roles' => env('WARDEN_SPATIE_ROLES_TABLE', 'roles'),
                'model_has_permissions' => env('WARDEN_SPATIE_MODEL_HAS_PERMISSIONS_TABLE', 'model_has_permissions'),
                'model_has_roles' => env('WARDEN_SPATIE_MODEL_HAS_ROLES_TABLE', 'model_has_roles'),
                'role_has_permissions' => env('WARDEN_SPATIE_ROLE_HAS_PERMISSIONS_TABLE', 'role_has_permissions'),
            ],
            'model_type' => env('WARDEN_SPATIE_MODEL_TYPE', 'user'),
        ],
    ],

];

// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ //
// Here endeth thy configuration, noble developer!                            //
// Beyond: code so wretched, even wyrms learned the scribing arts.            //
// Forsooth, they but penned "// TODO: remedy ere long"                       //
// Three realms have fallen since...                                          //
// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ //
//                                                  .~))>>                    //
//                                                 .~)>>                      //
//                                               .~))))>>>                    //
//                                             .~))>>             ___         //
//                                           .~))>>)))>>      .-~))>>         //
//                                         .~)))))>>       .-~))>>)>          //
//                                       .~)))>>))))>>  .-~)>>)>              //
//                   )                 .~))>>))))>>  .-~)))))>>)>             //
//                ( )@@*)             //)>))))))  .-~))))>>)>                 //
//              ).@(@@               //))>>))) .-~))>>)))))>>)>               //
//            (( @.@).              //))))) .-~)>>)))))>>)>                   //
//          ))  )@@*.@@ )          //)>))) //))))))>>))))>>)>                 //
//       ((  ((@@@.@@             |/))))) //)))))>>)))>>)>                    //
//      )) @@*. )@@ )   (\_(\-\b  |))>)) //)))>>)))))))>>)>                   //
//    (( @@@(.@(@ .    _/`-`  ~|b |>))) //)>>)))))))>>)>                      //
//     )* @@@ )@*     (@)  (@) /\b|))) //))))))>>))))>>                       //
//   (( @. )@( @ .   _/  /    /  \b)) //))>>)))))>>>_._                       //
//    )@@ (@@*)@@.  (6///6)- / ^  \b)//))))))>>)))>>   ~~-.                   //
// ( @jgs@@. @@@.*@_ VvvvvV//  ^  \b/)>>))))>>      _.     `bb                //
//  ((@@ @@@*.(@@ . - | o |' \ (  ^   \b)))>>        .'       b`,             //
//   ((@@).*@@ )@ )   \^^^/  ((   ^  ~)_        \  /           b `,           //
//     (@@. (@@ ).     `-'   (((   ^    `\ \ \ \ \|             b  `.         //
//       (*.@*              / ((((        \| | |  \       .       b `.        //
//                         / / (((((  \    \ /  _.-~\     Y,      b  ;        //
//                        / / / (((((( \    \.-~   _.`" _.-~`,    b  ;        //
//                       /   /   `(((((()    )    (((((~      `,  b  ;        //
//                     _/  _/      `"""/   /'                  ; b   ;        //
//                 _.-~_.-~           /  /'              _.'~bb _.'         //
//               ((((~~              / /'              _.'~bb.--~             //
//                                  ((((          __.-~bb.-~                  //
//                                              .'  b .~~                     //
//                                              :bb ,'                        //
//                                              ~~~~                          //
// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ //
