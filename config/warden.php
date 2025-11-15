<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Default Primary Key Type
    |--------------------------------------------------------------------------
    |
    | Here you may specify which type of primary key will be used for your
    | Warden database tables. The default 'id' type uses auto-incrementing
    | integers, which is suitable for most applications. Alternatively,
    | you may use 'ulid' for sortable unique identifiers, or 'uuid' for
    | universally unique identifiers.
    |
    | Supported: "id", "ulid", "uuid"
    |
    */

    'primary_key_type' => env('WARDEN_PRIMARY_KEY', 'id'),

    /*
    |--------------------------------------------------------------------------
    | User Model
    |--------------------------------------------------------------------------
    |
    | Here you may specify the fully-qualified class name of the User model
    | that will be used for role assignments. This model should exist in
    | your application and typically represents authenticated users.
    |
    */

    'user_model' => env('WARDEN_USER_MODEL', 'App\Models\User'),

    /*
    |--------------------------------------------------------------------------
    | Polymorphic Relationship Types
    |--------------------------------------------------------------------------
    |
    | The following options control the type of polymorphic relationships
    | used throughout Warden. These settings determine how foreign keys
    | are stored when tracking actors, contexts, and subjects in your
    | permission system. Each may be configured independently.
    |
    */

    'actor_morph_type' => env('WARDEN_ACTOR_MORPH_TYPE', 'morph'),

    'context_morph_type' => env('WARDEN_CONTEXT_MORPH_TYPE', 'morph'),

    'subject_morph_type' => env('WARDEN_SUBJECT_MORPH_TYPE', 'morph'),

    /*
    |--------------------------------------------------------------------------
    | Polymorphic Key Mapping
    |--------------------------------------------------------------------------
    |
    | When working with polymorphic relationships, you may specify which
    | column should be used as the foreign key for each model. This is
    | particularly useful in legacy systems where different models use
    | different primary key column names.
    |
    | Note: You may only use either 'morphKeyMap' or 'enforceMorphKeyMap',
    | not both. The enforced variant will throw an exception if a model
    | is used in a polymorphic relationship without an explicit mapping.
    |
    */

    'morphKeyMap' => [
        // App\Models\User::class => 'id',
        // App\Models\Organization::class => 'ulid',
    ],

    /*
    |--------------------------------------------------------------------------
    | Enforced Polymorphic Key Mapping
    |--------------------------------------------------------------------------
    |
    | This option works identically to 'morphKeyMap' but enables strict
    | enforcement. Any model referenced in a polymorphic relationship
    | without a defined mapping will throw a MorphKeyViolationException,
    | ensuring all models have explicit key mappings configured.
    |
    */

    'enforceMorphKeyMap' => [
        // App\Models\User::class => 'id',
        // App\Models\Organization::class => 'ulid',
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
