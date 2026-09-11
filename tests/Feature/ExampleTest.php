<?php

it('redirects guests from the home page to login', function () {
    $response = $this->get('/');

    $response->assertRedirect(route('login'));
});
