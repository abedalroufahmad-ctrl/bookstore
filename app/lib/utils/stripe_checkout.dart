bool isTrustedStripeCheckoutUrl(String raw) {
  final uri = Uri.tryParse(raw);
  if (uri == null) return false;
  return uri.scheme == 'https' && uri.host.toLowerCase() == 'checkout.stripe.com';
}