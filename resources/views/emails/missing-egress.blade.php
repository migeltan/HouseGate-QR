<p>Hi {{ $pass->visitor_name }},</p>
<p>Our records show you checked into {{ $pass->currentBuilding->name ?? 'a House of Representatives building' }}
but haven't scanned out yet. Please scan out at your earliest convenience.</p>
<p>— Legislative Security Bureau, House of Representatives</p>