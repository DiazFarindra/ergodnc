# TODO 1/9
- [x] Prepare migration
- [x] Seed the initial tags
- [x] Prepare models
- [x] Prepare factories
- [x] Prepare resources
- [x] Tags
    - Routes
    - Controller
    - Tests
- [x] Offices
    - List offices
    - Read office
    - Create office

# TODO 2/9

## List Offices Endpoint
- [x] Show only approvied and visible records
- [x] Filter by hosts
- [x] Filter by users
- [x] Include tags, images, and user
- [x] Show count of previous revervations
- [x] Paginate
- [x] Sort by distance if lng/lat provided, otherwise, oldest first
## Show office endpoint
- [x] Show count of previous resercations
- [x] Include tags, images, and user
## Create office endpoint
- [x] Host must be authenticated & email verified
- [x] cannot fill `approval_status`
- [x] Attach photos to offices endpoint

# TODO 3/9

## List Office Endpoint
- [x] Change the user_id filter to visitor_id and host_id to user_id
- [x] Switch to using custom polymorphic types
- [x] Order by distance but don't include the distance attribute
- [x] Configure the resources

## Create Office Endpoint
- [x] Host must be authenticated & email verified
- [x] Token (if exists) must allow `office.create`
- [x] Validation

# TODO 4/9
- [x] Office approval status should be pending or approved only ... no rejected
- [x] Store Office inside a database transaction

## Update Office Endpoint
- [x] Must be authenticated and email verified
- [x] Token if exists must allow `office.update`
- [x] Can only update their own offices
- [x] Validation
- [x] Mark as pending when critical attributes are updated and notify admin

## Create Office Endpoint
- [x] Notify admin on new office

## Delete Office Endpoint
- [x] Must be authenticated and email verified
- [x] Token (if exists) must allow `office.delete`
- [x] Can only delete their own offices
- [x] Cannot delete an office that has a reservation

## TODO 5/9
- [x] Identify who an admin is by adding an `is_admin` attribute to the user table
- [x] Show hidden and unapproved offices when filtering by `user_id` and the auth use matches the user so host can see all their listings

## Office Photos
- [x] Attaching photos to and office
- [x] Allow choosing a photo to become the featured photo
- [x] Deleting a photo
    - Must have at least one photo if it's approved

## TODO 6/9
- [x] Deleting all image when deleting an office
- [x] Use the default disk to store public image so it's easier to switch to different drivers in production
- [x] Use keyed implicit binding in the office image routes so laravel scope to the office that image belongs to [tweet](https://twitter.com/stellamatix)

## TODO 7/9
- [x] Switch to using sanctum guard by default
- [x] Use the new [assertNotSoftDeleted](https://github.com/laravel/framework/pull/38886) metod
- [x] Use the new LazilyRefreshDatabase testing trait on the base test class

## List Reservations Endpoint
- [x] Must be authenticated & email verified
- [x] Token (if exists) must allow `reservations.show`
- [x] Can only list their own reservations or reservations on their offices
- [x] Allow filtering by office_id
- [x] Allow filtering by user_id
- [x] Allow filtering by date range
- [x] Allow filtering by status
- [x] Paginate

## TODO 8/9
- [x] Convert filtering reservations by date to eloquent scopes
- [x] Include reservations that started before range and ended after range while filtering

## TODO 9/9
## Make Reservations Endpoint
- [x] Must be authenticated & email verified
- [x] Token (if exists) must allow `reservations.store`
- [x] Cannot make revervations on their own property
- [x] Validate no other reservations conflicts with the same time
- [x] Use locks to make the process atomic
- [x] Read request input from the validator output
- [x] You cant make a reservation on a pending or a hidden office
- [x] Test you can make reservation starting next day but cant make one on same day
- [x] Email user & host when reservations is made
- [x] Email user & host on reservations start day
- [x] Generate WIFI password for new reservations (store encrypted)

## Cancel Reservations Endpoint
- [x] Must be authenticated & email verified
- [x] Token (if exists) must allow `reservations.cancel`
- [x] Can only cancel their own reservation
- [x] Can only cancel an active reservation that has a start_date in the future

## Housekeeping
- [x] Convert filtering reservations by date to eloquent scopes
- [x] Filter offices by tag
- [x] API should return the full URI of the image so that the consumer can load it easily
