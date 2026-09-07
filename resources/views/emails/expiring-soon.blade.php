<p>Hi {{ $pass->visitor_name }},</p>
<p>Your long-term visitor pass is expected to return by {{ $pass->expected_return_date->format('F j, Y') }}
({{ $pass->daysRemaining() }} day(s) remaining).</p>
<p>— Legislative Security Bureau, House of Representatives</p>