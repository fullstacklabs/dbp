<?php

namespace Tests\Feature;

use App\Models\User\AccessGroup;
use App\Models\User\Project;
use App\Models\User\ProjectMember;
use App\Models\User\ProjectOauthProvider;
use Illuminate\Foundation\Testing\WithFaker;

class v4_userRoutesTest extends API_V4_Test
{
    use WithFaker;

	public function test_v4_access_groups()
	{
		/**@category V4_API
		 * @category Route Name: v4_access_groups.index
		 * @category Route Path: https://api.dbp.test/access/groups?v=4&key=1234
		 * @see      \App\Http\Controllers\User\AccessGroupController::index
		 */
		$path = route('v4_access_groups.index', $this->params);
		echo "\nTesting: $path";
		$response = $this->withHeaders($this->params)->get($path);
		$response->assertSuccessful();

		/**@category V4_API
		 * @category Route Name: v4_access_groups.show
		 * @category Route Path: https://api.dbp.test/access/groups/{access_group_id}?v=4&key=1234&pretty
		 * @see      \App\Http\Controllers\User\AccessGroupController::show
		 */
		$group = AccessGroup::inRandomOrder()->first();
		$path              = route('v4_access_groups.show', array_add($this->params, 'id', $group->name));
		echo "\nTesting: $path";
		$response = $this->withHeaders($this->params)->get($path);
		$response->assertSuccessful();
	}

	public function test_v4_resources()
	{
		/**
		 * @category V4_API
		 * @category Route Name: v4_resources.index
		 * @category Route Path: https://api.dbp.test/resources?v=4&key=1234
		 * @see      \App\Http\Controllers\Organization\ResourcesController::index
		 */
		$path = route('v4_resources.index', $this->params);
		echo "\nTesting: $path";
		$response = $this->withHeaders($this->params)->get($path);
		$response->assertSuccessful();

		/**
		 * @category V4_API
		 * @category Route Name: v4_resources.show
		 * @category Route Path: https://api.dbp.test/resources/{resource_id}?v=4&key=1234
		 * @see      \App\Http\Controllers\Organization\ResourcesController::show
		 */
		$path = route('v4_resources.show', $this->params);
		echo "\nTesting: $path";
		$response = $this->withHeaders($this->params)->get($path);
		$response->assertSuccessful();


		/**
		 * @category V4_API
		 * @category Route Name: v4_resources.update
		 * @category Route Path: https://api.dbp.test/resources/{resource_id}?v=4&key=1234
		 * @see      \App\Http\Controllers\Organization\ResourcesController::update
		 */
		$path = route('v4_resources.update', $this->params);
		echo "\nTesting: $path";
		$response = $this->withHeaders($this->params)->get($path);
		$response->assertSuccessful();


		/**
		 * @category V4_API
		 * @category Route Name: v4_resources.store
		 * @category Route Path: https://api.dbp.test/resources?v=4&key=1234
		 * @see      \App\Http\Controllers\Organization\ResourcesController::store
		 */
		$path = route('v4_resources.store', $this->params);
		echo "\nTesting: $path";
		$response = $this->withHeaders($this->params)->get($path);
		$response->assertSuccessful();

		/**
		 * @category V4_API
		 * @category Route Name: v4_resources.destroy
		 * @category Route Path: https://api.dbp.test/resources/{resource_id}?v=4&key=1234
		 * @see      \App\Http\Controllers\Organization\ResourcesController::destroy
		 */
		$path = route('v4_resources.destroy', $this->params);
		echo "\nTesting: $path";
		$response = $this->withHeaders($this->params)->get($path);
		$response->assertSuccessful();
	}


	public function test_v4_users()
	{

        $project_id = Project::inRandomOrder()->first()->id;

		/**
		 * @category V4_API
		 * @category Route Name: v4_user.index
		 * @category Route Path: https://api.dbp.test/users?v=4&key=1234
		 * @see      \App\Http\Controllers\User\UsersController::index
		 */
		$path = route('v4_user.index', array_add($this->params, 'project_id', $project_id));
		echo "\nTesting: $path";
		$response = $this->withHeaders($this->params)->get($path);
		$response->assertSuccessful();

		/**
		 * @category V4_API
		 * @category Route Name: v4_user.store
		 * @category Route Path: https://api.dbp.test/users?v=4&key=1234
		 * @see      \App\Http\Controllers\User\UsersController::store
		 */
		$new_user = [
			'avatar'      => 'example.com/avatar.jpg',
			'email'       => $this->faker->email(),
			'name'        => $this->faker->name(),
			'notes'       => 'A user generated by Feature Tests',
			'password'    => 'test_1234',
			'project_id'  => $project_id
		];
		$path = route('v4_user.store', $this->params);
		echo "\nTesting: $path";
		$response = $this->withHeaders($this->params)->post($path,$new_user);
		$response->assertSuccessful();

		// Ensure the new user matches the input
		$new_created_user = json_decode($response->getContent());
        $new_created_user = $new_created_user->data;

		$this->assertSame($new_user['avatar'], $new_created_user->avatar);
		$this->assertSame($new_user['email'], $new_created_user->email);
		$this->assertSame($new_user['name'], $new_created_user->name);

		/**
		 * @category V4_API
		 * @category Route Name: v4_user.update
		 * @category Route Path: https://api.dbp.test/users/{user_id}?v=4&key=1234
		 * @see      \App\Http\Controllers\User\UsersController::update
		 */
		$path = route('v4_user.update', array_merge(['user_id' => $new_created_user->id,'project_id' => $project_id],$this->params));
		echo "\nTesting: $path";
		$response = $this->withHeaders($this->params)->put($path, ['notes' => 'A user updated by Feature tests']);
		$response->assertSuccessful();

		/**
		 * @category V4_API
		 * @category Route Name: v4_user.show
		 * @category Route Path: https://api.dbp.test/users/1096385?v=4&key=1234
		 * @see      \App\Http\Controllers\User\UsersController::show
		 */
		$path = route('v4_user.show', array_merge(['user_id' => $new_created_user->id,'project_id' => $project_id],$this->params));
		echo "\nTesting: $path";
		$response = $this->withHeaders(array_merge(['user_id' => $new_created_user->id,'project_id' => $project_id],$this->params))->get($path);
		$response->assertSuccessful();

		/**
		 * @category V4_API
		 * @category Route Name: v4_user.destroy
		 * @category Route Path: https://api.dbp.test/users/{user_id}?v=4&key=1234
		 * @see      \App\Http\Controllers\User\UsersController::destroy
		 */
		$path = route('v4_user.destroy', array_merge(['user_id' => $new_created_user->id,'project_id' => $project_id],$this->params));
		echo "\nTesting: $path";
		$response = $this->withHeaders($this->params)->delete($path);
		$response->assertSuccessful();
	}

	/**
	 * @category V4_API
	 * @category Route Name: v4_user.login
	 * @category Route Path: https://api.dbp.test/users/login?v=4&key=1234
	 * @see      \App\Http\Controllers\User\UsersController::login
	 */
	public function test_v4_user_login()
	{
		$login = ['email' => 'jonbitgood@gmail.com', 'password' => 'test_password123'];

		$path = route('v4_user.login', $this->params);
		echo "\nTesting Login Via Password: $path";
		$response = $this->withHeaders($this->params)->post($path, $login);
		$response->assertSuccessful();
	}

	/**
	 * @category V4_API
	 * @category Route Name: v4_user.geolocate
	 * @category Route Path: https://api.dbp.test/users/geolocate?v=4&key=1234
	 * @see      \App\Http\Controllers\User\UsersController::geoLocate
	 */
	public function test_v4_user_geolocate()
	{
		$path = route('v4_user.geolocate', $this->params);
		echo "\nTesting: $path";
		$response = $this->withHeaders($this->params)->get($path);
		$response->assertSuccessful();
	}

	/**
     *
     *
	 * @category V4_API
	 * @category Route Name: v4_user.oAuth
	 * @category Route Path: https://api.dbp.test/users/login/{driver}?v=4&key=1234
	 * @see      \App\Http\Controllers\User\UserSocialController::getSocialRedirect
     */
	public function test_v4_user_oAuth()
	{
	    $projectOAuth = ProjectOauthProvider::inRandomOrder()->first();
		$path = route('v4_user.oAuth', array_merge($this->params,['driver' => $projectOAuth->name, 'project_id' => $projectOAuth->project_id]));
		echo "\nTesting: $path";
		$response = $this->withHeaders($this->params)->get($path);
		$response->assertSuccessful();
	}


	/**
     *
     * // TODO: create custom Oauth Providers for external testing
     *
	 * @category V4_API
	 * @category Route Name: v4_user.oAuthCallback
	 * @category Route Path: https://api.dbp.test/users/login/{driver}/callback?v=4&key=1234
	 * @see      \App\Http\Controllers\User\UserSocialController::handleProviderCallback()

	public function test_v4_user_oAuthCallback()
	{
	    $projectOauthProvider = ProjectOauthProvider::inRandomOrder()->first();
	    $additional_params = [
            'provider'   => $projectOauthProvider->name,
	        'project_id' => $projectOauthProvider->project_id
        ];
		$path = route('v4_user.oAuthCallback', array_merge($this->params, $additional_params));
		echo "\nTesting: $path";
		$response = $this->withHeaders($this->params)->get($path);
		$response->assertSuccessful();
	}
     */
	/**
	 * @category V4_API
	 * @category Route Name: v4_user.password_reset
	 * @category Route Path: https://api.dbp.test/users/password/reset?v=4&key=1234
	 * @see      \App\Http\Controllers\User\UserPasswordsController::validatePasswordReset
	 */
	public function test_v4_user_password_reset()
	{
		$account = [
			'new_password'              => 'test_password123',
			'new_password_confirmation' => 'test_password123',
			'token_id'                  => '12345',
			'email'                     => 'jonbitgood@gmail.com',
			'project_id'                => '52341',
		];
		$path = route('v4_user.password_reset', $this->params);
		echo "\nTesting: $path";
		$response = $this->withHeaders($this->params)->post($path,$account);
		$response->assertSuccessful();
	}

	/**
	 * @category V4_API
	 * @category Route Name: v4_user.password_email
	 * @category Route Path: https://api.dbp.test/users/password/email?v=4&key=1234
	 * @see      \App\Http\Controllers\User\UserPasswordsController::triggerPasswordResetEmail
	 */
	public function test_v4_user_password_email()
	{

		$path = route('v4_user.password_email', $this->params);
		echo "\nTesting: $path";
		$response = $this->withHeaders($this->params)->post($path, ['email' => 'jonbitgood@gmail.com', 'project_id' => '52341']);
		$response->assertSuccessful();
	}


	public function test_v4_user_accounts()
	{

		/**
		 * @category V4_API
		 * @category Route Name: v4_user_accounts.index
		 * @category Route Path: https://api.dbp.test/accounts?v=4&key=1234
		 * @see      \App\Http\Controllers\User\UserAccountsController::index
		 */
		$project_connection = ProjectMember::inRandomOrder()->first();
		$project_fields = ['project_id' => $project_connection->project_id, 'user_id' => $project_connection->user_id];
		$path = route('v4_user_accounts.index', array_merge($project_fields,$this->params));
		echo "\nTesting: $path";
		$response = $this->withHeaders($this->params)->get($path);
		$response->assertSuccessful();

		/**
		 * @category V4_API
		 * @category Route Name: v4_user_accounts.store
		 * @category Route Path: https://api.dbp.test/accounts?v=4&key=1234
		 * @see      \App\Http\Controllers\User\UserAccountsController::store
		 */
		$path = route('v4_user_accounts.store', array_merge($project_fields,$this->params));
		echo "\nTesting: $path";
		$account = [
			'user_id' => '5',
			'provider_id' => 'test',
			'provider_user_id' => '8179004',
		];
		$response = $this->withHeaders($this->params)->post($path, $account);
		$response->assertSuccessful();

		$test_account = json_decode($response->getContent());

		/**
		 * @category V4_API
		 * @category Route Name: v4_user_accounts.show
		 * @category Route Path: https://api.dbp.test//accounts/{account_id}?v=4&key=1234
		 * @see      \App\Http\Controllers\User\UserAccountsController::show
		 */
		$project_fields = array_add($project_fields,'account_id',$test_account->id);
		$path = route('v4_user_accounts.show', array_merge($project_fields,$this->params));
		echo "\nTesting: $path";
		$response = $this->withHeaders($this->params)->get($path);
		$response->assertSuccessful();

		/**
		 * @category V4_API
		 * @category Route Name: v4_user_accounts.update
		 * @category Route Path: https://api.dbp.test//accounts/{account_id}?v=4&key=1234
		 * @see      \App\Http\Controllers\User\UserAccountsController::update
		 */
		$path = route('v4_user_accounts.update', array_merge($project_fields,$this->params));
		echo "\nTesting: $path";
		$response = $this->withHeaders($this->params)->put($path, ['provider_user_id' => 'aiorniga']);
		$response->assertSuccessful();

		/**
		 * @category V4_API
		 * @category Route Name: v4_user_accounts.destroy
		 * @category Route Path: https://api.dbp.test/accounts/{account_id}?v=4&key=1234
		 * @see      \App\Http\Controllers\User\UserAccountsController::destroy
		 */
		$path = route('v4_user_accounts.destroy', array_merge($project_fields,$this->params));
		echo "\nTesting: $path";
		$response = $this->withHeaders($this->params)->delete($path);
		$response->assertSuccessful();
	}


	public function test_v4_notes()
	{
		/**
		 * @category V4_API
		 * @category Route Name: v4_notes.index
		 * @category Route Path: https://api.dbp.test/users/5/notes?v=4&key=1234
		 * @see      \App\Http\Controllers\User\UserNotesController::index
		 */
		$path = route('v4_notes.index', array_add($this->params, 'user_id', '5'));
		echo "\nTesting: $path";
		$response = $this->withHeaders($this->params)->get($path);
		$response->assertSuccessful();

		/**
		 * @category V4_API
		 * @category Route Name: v4_notes.show
		 * @category Route Path: https://api.dbp.test/users/5/notes/127?v=4&key=1234
		 * @see      \App\Http\Controllers\User\UserNotesController::show
		 */
		$path = route('v4_notes.show', array_merge(['user_id' => 5,'note_id' => 127],$this->params));
		echo "\nTesting: $path";
		$response = $this->withHeaders($this->params)->get($path);
		$response->assertSuccessful();

		/**
		 * @category V4_API
		 * @category Route Name: v4_notes.store
		 * @category Route Path: https://api.dbp.test/users/5/notes?v=4&key=1234
		 * @see      \App\Http\Controllers\User\UserNotesController::store
		 */
		$test_note = [
			'user_id' => 5,
			'bible_id' => 'ENGESV',
			'book_id' => 'GEN',
			'chapter' => 1,
			'verse_start' => 1,
			'verse_end' => 2,
			'notes' => 'A generated test note',
		];
		$path = route('v4_notes.store', $this->params);
		echo "\nTesting: $path";
		$response = $this->withHeaders($this->params)->post($path, $test_note);
		$response->assertSuccessful();

		$test_created_note = json_decode($response->getContent())->data;

		/**
		 * @category V4_API
		 * @category Route Name: v4_notes.update
		 * @category Route Path: https://api.dbp.test/users/{user_id}/notes/{note_id}?v=4&key=1234
		 * @see      \App\Http\Controllers\User\UserNotesController::update
		 */
		$path = route('v4_notes.update', array_merge(['user_id' => 5,'note_id' => $test_created_note->id],$this->params));
		echo "\nTesting: $path";
		$response = $this->withHeaders($this->params)->put($path, ['description' => 'A generated test note that has been updated']);
		$response->assertSuccessful();

		/**
		 * @category V4_API
		 * @category Route Name: v4_notes.destroy
		 * @category Route Path: https://api.dbp.test/users/{user_id}/notes/{note_id}?v=4&key=1234
		 * @see      \App\Http\Controllers\User\UserNotesController::destroy
		 */
		$path = route('v4_notes.destroy', array_merge(['user_id' => 5,'note_id' => $test_created_note->id],$this->params));
		echo "\nTesting: $path";
		$response = $this->withHeaders($this->params)->delete($path);
		$response->assertSuccessful();
	}

	/**
	 * @category V4_API
	 * @category Route Name: v4_messages.index
	 * @category Route Path: https://api.dbp.test/users/messages?v=4&key=1234
	 * @see      \App\Http\Controllers\User\UserContactController::index

	public function test_v4_messages_index()
	{
		$path = route('v4_messages.index', $this->params);
		echo "\nTesting: $path";
		$response = $this->withHeaders($this->params)->get($path);
		$response->assertSuccessful();
	}
	 */

	/**
	 * @category V4_API
	 * @category Route Name: v4_messages.show
	 * @category Route Path: https://api.dbp.test/users/messages/{note_id}?v=4&key=1234
	 * @see      \App\Http\Controllers\User\UserContactController::show

	public function test_v4_messages_show()
	{
		$path = route('v4_messages.show', $this->params);
		echo "\nTesting: $path";
		$response = $this->withHeaders($this->params)->get($path);
		$response->assertSuccessful();
	}
	 * */


	public function test_v4_bookmarks()
	{
		/**
		 * @category V4_API
		 * @category Route Name: v4_bookmarks.index
		 * @category Route Path: https://api.dbp.test/users/{user_id}/bookmarks?v=4&key=1234
		 * @see      \App\Http\Controllers\User\UserBookmarksController::index
		 */
		$path = route('v4_bookmarks.index', array_add($this->params,'user_id',5));
		echo "\nTesting: $path";
		$response = $this->withHeaders($this->params)->get($path);
		$response->assertSuccessful();

		/**
		 * @category V4_API
		 * @category Route Name: v4_bookmarks.store
		 * @category Route Path: https://api.dbp.test/users/{user_id}/bookmarks?v=4&key=1234
		 * @see      \App\Http\Controllers\User\UserBookmarksController::store
		 */
		$test_bookmark = [
			'bible_id'      => 'ENGESV',
			'user_id'       => 5,
			'book_id'       => 'GEN',
			'chapter'       => 1,
			'verse_start'   => 10,
		];
		$path = route('v4_bookmarks.store', array_add($this->params,'user_id',5));
		echo "\nTesting: $path";
		$response = $this->withHeaders($this->params)->post($path, $test_bookmark);
		$response->assertSuccessful();

		$test_bookmark = json_decode($response->getContent())->data;

		/**
		 * @category V4_API
		 * @category Route Name: v4_bookmarks.update
		 * @category Route Path: https://api.dbp.test/users/{user_id}/bookmarks/{bookmark_id}?v=4&key=1234
		 * @see      \App\Http\Controllers\User\UserBookmarksController::update
		 */
		$path = route('v4_bookmarks.update', array_merge(['user_id' => 5,'bookmark_id' =>$test_bookmark->id],$this->params));
		echo "\nTesting: $path";
		$response = $this->withHeaders($this->params)->put($path,['book_id' => 'EXO']);
		$response->assertSuccessful();

		/**
		 * @category V4_API
		 * @category Route Name: v4_bookmarks.destroy
		 * @category Route Path: https://api.dbp.test/users/{user_id}/bookmarks/{bookmark_id}?v=4&key=1234
		 * @see      \App\Http\Controllers\User\UserBookmarksController::destroy
		 */
		$path = route('v4_bookmarks.destroy', array_merge(['user_id' => 5,'bookmark_id' =>$test_bookmark->id],$this->params));
		echo "\nTesting: $path";
		$response = $this->withHeaders($this->params)->delete($path);
		$response->assertSuccessful();
	}


	public function test_v4_highlights()
	{
		/**
		 * @category V4_API
		 * @category Route Name: v4_highlights.index
		 * @category Route Path: https://api.dbp.test/users/{user_id}/highlights?v=4&key=1234
		 * @see      \App\Http\Controllers\User\UserHighlightsController::index
		 */
		$path = route('v4_highlights.index', array_add($this->params,'user_id', 5));
		echo "\nTesting: $path";
		$response = $this->withHeaders($this->params)->get($path);
		$response->assertSuccessful();

		/**
		 * @category V4_API
		 * @category Route Name: v4_highlights.store
		 * @category Route Path: https://api.dbp.test/users/{user_id}/highlights?v=4&key=1234
		 * @see      \App\Http\Controllers\User\UserHighlightsController::store
		 */
		$test_highlight_post = [
			'bible_id'          => 'ENGESV',
			'user_id'           => 5,
			'book_id'           => 'GEN',
			'chapter'           => '1',
			'verse_start'       => '1',
			'reference'         => 'Genesis 1:1',
			'highlight_start'   => '10',
			'highlighted_words' => '40',
			'highlighted_color' => '#fff000',
		];

		$path = route('v4_highlights.store', array_add($this->params,'user_id', 5));
		echo "\nTesting: $path";
		$response = $this->withHeaders($this->params)->post($path, $test_highlight_post);
		$response->assertSuccessful();

		$test_highlight = json_decode($response->getContent())->data;

		/**
		 * @category V4_API
		 * @category Route Name: v4_highlights.update
		 * @category Route Path: https://api.dbp.test/users/{user_id}/highlights/{highlight_id}?v=4&key=1234
		 * @see      \App\Http\Controllers\User\UserHighlightsController::update
		 */
		$path = route('v4_highlights.update', array_merge(['user_id' => 5,'highlight_id' => $test_highlight->id], $this->params));
		echo "\nTesting: $path";
		$response = $this->withHeaders($this->params)->put($path,['highlighted_color' => '#ff1100']);
		$response->assertSuccessful();

		/**
		 * @category V4_API
		 * @category Route Name: v4_highlights.destroy
		 * @category Route Path: https://api.dbp.test/users/{user_id}/highlights/{highlight_id}?v=4&key=1234
		 * @see      \App\Http\Controllers\User\UserHighlightsController::destroy
		 */
		$path = route('v4_highlights.destroy', array_merge(['user_id' => 5,'highlight_id' => $test_highlight->id], $this->params));
		echo "\nTesting: $path";
		$response = $this->withHeaders($this->params)->delete($path);
		$response->assertSuccessful();
	}


}