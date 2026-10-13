<?php
Auth::logout();
flash('success', 'You have been logged out.');
redirect(pageUrl('home'));
