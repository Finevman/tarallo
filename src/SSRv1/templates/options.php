<?php
/** @var \WEEEOpen\Tarallo\User $user */
/** @var string|null $result */
$this->layout('main', ['title' => 'Change password', 'user' => $user]);
?>
<div id="options">
	<form action="/options" method="POST">
		<?php if($result !== null):
			switch($result):
				case 'empty':
					?><p class="error message">Password cannot be empty</p><?php
					break;
				case 'nomatch':
					?><p class="error message">Password and confirmation don't match</p><?php
					break;
				case 'short':
					?><p class="error message">Password too short</p><?php
					break;
				case 'success':
					?><p class="success message">Password changed successfully</p><?php
					break;
				case 'successnew':
					?><p class="success message">User created successfully</p><?php
					break;
				default:
					?><p class="error message">Unknown error</p><?php
					break;
			endswitch;
		endif; ?>
		<?php if($user->getLevel() <= 0): ?>
		<label>Username: <input type="text" name="username" placeholder="<?=$user->getUsername()?>"></label>
		<?php endif; ?>
		<label>New password: <input type="password" name="password"></label>
		<label>Confirm: <input type="password" name="confirm"></label>
		<input type="submit" value="Submit">
	</form>
</div>