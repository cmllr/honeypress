<form method="POST">
<table class="form-table">
	<tbody><tr>
		<th><label for="splunk_url">Splunk URL</label></th>
		<td> <input name="splunk_url" id="splunk_url" value="<?php echo $url;?>"" class="regular-text code" type="text"></td>
	</tr>
	<tr>
		<th><label for="splunk_token">Splunk Token</label></th>
		<td> <input name="splunk_token" id="splunk_token" value="<?php echo $token;?>" class="regular-text code" type="text"></td>
	</tr>
	</tbody>
</table>


<p class="submit">
<input name="submit" id="submit" class="button button-primary" value="save" type="submit">
</p>
</form>