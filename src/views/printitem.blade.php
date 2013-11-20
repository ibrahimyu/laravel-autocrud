<title>@yield('title')</title>
<link rel="stylesheet" type="text/css" href="/bundles/bootstrapper/css/bootstrap.min.css"/>
<link rel="stylesheet" type="text/css" href="/css/style2.css"/>
<style>
body { background: none; margin: 5%; }
</style>

<style>
	.boxed {
		padding: 10px 0;
		border-radius: 0;
	}
	.bordered {
		border: 1px solid black;
	}
	body, h1, h2, h3, h4, h5, h6 {
		font-family: "Tahoma", sans-serif;
	}
	body, td {
		font-size: 9.5pt;
	}
</style>

@yield('content')