(function($) {
  var wp = window.wp;
  if ( ! wp || ! wp.heartbeat )
    return;

  wp.heartbeat.interval('fast');

  var $document = $(document),
    attendees = [];

  $document.on('ready heartbeat-send', function() {
    wp.heartbeat.enqueue('battleship', {attendance: true});
  }).on('heartbeat-tick', function(data, response) {
    if ( ! response || ! response.battleship)
      return;

    if (response.battleship.log_me_out) {
      var logoutUrl = $('#wp-admin-bar-logout').find('a').attr('href');
      alert('Your battlehip was sunk :/');
      window.location = logoutUrl;
    }

    attendees = response.battleship.attendees;
  });

  new Konami(function() {
    if (! attendees.length)
      return alert("We don't have anyone to sink...yet.");

    var selection = prompt('Who should we log out?: ' + attendees.join(', '));
    if (attendees.indexOf(selection) == -1)
      return alert('Nope');

    $.post(ajaxurl, {
      action: 'battleship_logout',
      name: selection,
      _nonce: battleship.nonce
    });

    alert('Okay, logging out ' + selection);
  });
})(jQuery);
