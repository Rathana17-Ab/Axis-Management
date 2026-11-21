<?php

test('the application root redirects to the Axis project list', function () {
    $response = $this->get('/');

    $response->assertRedirect('/admin/project');
});
