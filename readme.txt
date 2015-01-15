=== Rails Login ===
Contributors: paulrosen
Donate link: http://paulrosen.net/rails_login
Tags: authentication, login, rails
Requires at least: 4.0.0
Tested up to: 4.1.0
Stable tag: 1.0.0

The Rails Login plugin enables a WordPress installation that is in a subfolder of a
Rails application to use the Rails authentication instead of its own.

== Description ==

If you have set up a WordPress installation as a subfolder of a Rails App, this
plugin will make your users login with your Rails app. That is, there is no
WordPress login; your users will be logged in if they are logged in to Rails, and
they are not logged in if not.

The WordPress installation needs to be on the same domain as the Rails app
because it needs to access the same cookie.

== Installation ==

1. Be sure that the Rails side is set up and working. See FAQ for more details.
1. Unzip this plugin folder and copy the *rails-login* subfolder to your WordPress's plugin directory.
1. Activate the plugin through the 'Plugins' menu in WordPress.
1. Configure the settings in the 'Settings/Rails Login' page.

== Frequently Asked Questions ==

= How should the Rails app be set up? =

You must first configure your Rails app and WordPress installation to work together.
For instructions on how to do that, and a possible way to share a theme between
WordPress and Rails is here: [Rails Theme](https://wordpress.org/plugins/rails-theme/)

In your routes.rb file:

get '/authentication/user' => "authentication#user"

after logging in and logging out, insert this statement:

write_user_cookie()

Add this to authentication_controller.rb:

	#GET /authentication/user.json
	def user
		user = read_user_cookie(params[:id])
		obj = { user: user }
		puts obj.to_json()
		respond_to do |format|
			format.json { render :json => { user: user } }
		end
	end

	private

	def get_crypt
		key = ActiveSupport::KeyGenerator.new(Rails.application.secrets.secret_key_base).generate_key("read-current-user")
		crypt = ActiveSupport::MessageEncryptor.new(key)
		return crypt
	end

	def write_user_cookie
		crypt = get_crypt()
		cookie_name = Rails.application.config.session_options[:key] + '2'
		id = current_user.present? ? current_user.id : 0
		cookies[cookie_name] = crypt.encrypt_and_sign(id)
	end

	def read_user_cookie(cookie)
		crypt = get_crypt()
		id = crypt.decrypt_and_verify(cookie)

		user = User.where(id: id).first
		if user.present? && !user.disabled
			name = user.first_name + ' ' + user.last_name
			return { email: user.email,
					 first_name: user.first_name,
					 last_name: user.last_name,
					 url: '', description: '',
					 username: user.email,
					 login: user.email,
					 nickname: name,
					 display_name: name
			}
		else
			return ''
		end
	end

== Screenshots ==

1. Settings page for administrators.

== Changelog ==

= 1.0.0 =
* Initial release.
