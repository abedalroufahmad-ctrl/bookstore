import 'package:flutter/material.dart';
import 'package:getwidget/getwidget.dart';
import 'package:provider/provider.dart';

import '../api/api_service.dart';
import '../l10n/app_localizations.dart';
import '../providers/auth_provider.dart';
import '../providers/locale_provider.dart';
import '../providers/profile_provider.dart';

/// Profile screen: personal info, shipping address, preferences, logout.
/// Matches the reference design with section cards and edit for shipping/communication data.
class AccountScreen extends StatelessWidget {
  const AccountScreen({super.key});

  static const String _appVersion = 'v0.1.0';

  @override
  Widget build(BuildContext context) {
    final t = AppLocalizations.of(context);
    final theme = Theme.of(context);
    return Scaffold(
      appBar: AppBar(
        title: Text(t.myProfile),
        centerTitle: true,
        actions: [
          Consumer<AuthProvider>(
            builder: (context, auth, _) {
              if (!auth.isLoggedIn) return const SizedBox.shrink();
              return IconButton(
                icon: const Icon(Icons.edit_outlined),
                onPressed: () => _showEditProfileSheet(context),
                tooltip: t.editProfile,
              );
            },
          ),
        ],
      ),
      body: Consumer<AuthProvider>(
        builder: (context, auth, _) {
          if (!auth.isLoggedIn) {
            return _buildGuestContent(context, t);
          }
          return SingleChildScrollView(
            padding: const EdgeInsets.fromLTRB(16, 8, 16, 24),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                _buildSectionTitle(context, t.personalInformation),
                _buildPersonalInfoCard(context, auth, t),
                const SizedBox(height: 24),
                _buildSectionTitle(context, t.shippingAddressSection),
                _buildShippingCard(context, t),
                const SizedBox(height: 24),
                _buildSectionTitle(context, t.preferences),
                _buildPreferencesCard(context, t),
                const SizedBox(height: 24),
                _buildLogoutButton(context, auth, t),
                const SizedBox(height: 16),
                Center(
                  child: Text(
                    '${t.appVersion} $_appVersion',
                    style: theme.textTheme.bodySmall?.copyWith(
                      color: theme.colorScheme.onSurface.withValues(alpha: 0.5),
                    ),
                  ),
                ),
              ],
            ),
          );
        },
      ),
    );
  }

  Widget _buildGuestContent(BuildContext context, AppLocalizations t) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Text(
              t.loginRequired,
              style: Theme.of(context).textTheme.bodyLarge,
              textAlign: TextAlign.center,
            ),
            const SizedBox(height: 24),
            GFButton(
              onPressed: () => Navigator.pushNamed(context, '/login'),
              icon: const Icon(Icons.login, color: Colors.white),
              text: t.navLogin,
              fullWidthButton: true,
              size: GFSize.LARGE,
              color: Theme.of(context).colorScheme.primary,
            ),
            const SizedBox(height: 12),
            GFButton(
              onPressed: () => Navigator.pushNamed(context, '/register'),
              text: t.navRegister,
              type: GFButtonType.outline,
              fullWidthButton: true,
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildSectionTitle(BuildContext context, String title) {
    return Padding(
      padding: const EdgeInsets.only(left: 4, bottom: 8),
      child: Text(
        title,
        style: Theme.of(context).textTheme.titleMedium?.copyWith(
              fontWeight: FontWeight.bold,
              color: Theme.of(context).colorScheme.onSurface,
            ),
      ),
    );
  }

  Widget _buildPersonalInfoCard(BuildContext context, AuthProvider auth, AppLocalizations t) {
    final profile = context.watch<ProfileProvider>();
    final theme = Theme.of(context);
    final customer = auth.customer;
    final name = customer?.name ?? t.notSet;
    final email = customer?.email ?? t.notSet;
    final phone = profile.phone ?? t.notSet;

    return GFCard(
      elevation: 0,
      padding: EdgeInsets.zero,
      margin: EdgeInsets.zero,
      borderRadius: BorderRadius.circular(12),
      content: Column(
        children: [
          _profileRow(theme, Icons.person_outline, t.nameLabel, name),
          _divider(),
          _profileRow(theme, Icons.phone_outlined, t.phoneLabel, phone),
          _divider(),
          _profileRow(theme, Icons.email_outlined, t.emailLabel, email),
        ],
      ),
    );
  }

  Widget _profileRow(ThemeData theme, IconData icon, String label, String value) {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
      child: Row(
        children: [
          Icon(icon, size: 22, color: theme.colorScheme.primary),
          const SizedBox(width: 16),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(
                  label,
                  style: theme.textTheme.bodySmall?.copyWith(
                    color: theme.colorScheme.onSurface.withValues(alpha: 0.6),
                  ),
                ),
                const SizedBox(height: 2),
                Text(
                  value,
                  style: theme.textTheme.bodyLarge?.copyWith(
                    fontWeight: FontWeight.w500,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _divider() {
    return Divider(height: 1, indent: 54, endIndent: 16);
  }

  Widget _buildShippingCard(BuildContext context, AppLocalizations t) {
    final profile = context.watch<ProfileProvider>();
    final theme = Theme.of(context);

    return GFCard(
      elevation: 0,
      padding: EdgeInsets.zero,
      margin: EdgeInsets.zero,
      borderRadius: BorderRadius.circular(12),
      content: Column(
        children: [
          _profileRow(theme, Icons.location_on_outlined, t.addressLabel, profile.address ?? t.notSet),
          _divider(),
          _profileRow(theme, Icons.location_city_outlined, t.cityLabel, profile.city ?? t.notSet),
          _divider(),
          _profileRow(theme, Icons.tag_outlined, t.postalCodeLabel, profile.postalCode ?? t.notSet),
          _divider(),
          _profileRow(theme, Icons.public_outlined, t.countryLabel, profile.country ?? t.notSet),
        ],
      ),
    );
  }

  Widget _buildPreferencesCard(BuildContext context, AppLocalizations t) {
    final theme = Theme.of(context);
    final locale = context.watch<LocaleProvider>();

    return GFCard(
      elevation: 0,
      padding: EdgeInsets.zero,
      margin: EdgeInsets.zero,
      borderRadius: BorderRadius.circular(12),
      content: Column(
        children: [
          GFListTile(
            icon: Icon(Icons.language, size: 22, color: theme.colorScheme.primary),
            titleText: t.language,
            subTitleText: locale.languageCode == 'ar' ? 'العربية' : 'English',
            onTap: () => locale.toggleLanguage(),
          ),
          const Divider(height: 1),
          GFListTile(
            icon: Icon(Icons.receipt_long_outlined, size: 22, color: theme.colorScheme.primary),
            titleText: t.myOrders,
            subTitleText: t.viewAndManageOrders,
            onTap: () => Navigator.pushNamed(context, '/orders'),
          ),
        ],
      ),
    );
  }

  Widget _buildLogoutButton(BuildContext context, AuthProvider auth, AppLocalizations t) {
    return GFButton(
      onPressed: () {
        auth.logout();
        Navigator.pushNamedAndRemoveUntil(context, '/', (_) => false);
      },
      text: t.navLogout,
      fullWidthButton: true,
      size: GFSize.LARGE,
      shape: GFButtonShape.pills,
      color: Theme.of(context).colorScheme.primary,
    );
  }

  static void _showEditProfileSheet(BuildContext context) {
    final t = AppLocalizations.of(context);
    final profile = context.read<ProfileProvider>();
    final phoneController = TextEditingController(text: profile.phone);
    final addressController = TextEditingController(text: profile.address);
    final cityController = TextEditingController(text: profile.city);
    final countryController = TextEditingController(text: profile.country);
    final postalController = TextEditingController(text: profile.postalCode);

    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(16)),
      ),
      builder: (ctx) {
        return Padding(
          padding: EdgeInsets.only(bottom: MediaQuery.of(ctx).viewInsets.bottom),
          child: SingleChildScrollView(
            padding: const EdgeInsets.all(24),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Text(t.editProfile, style: Theme.of(ctx).textTheme.titleLarge),
                const SizedBox(height: 20),
                TextFormField(
                  controller: phoneController,
                  decoration: InputDecoration(labelText: t.phoneLabel),
                  keyboardType: TextInputType.phone,
                ),
                const SizedBox(height: 16),
                TextFormField(
                  controller: addressController,
                  decoration: InputDecoration(labelText: t.addressLabel),
                ),
                const SizedBox(height: 16),
                TextFormField(
                  controller: cityController,
                  decoration: InputDecoration(labelText: t.cityLabel),
                ),
                const SizedBox(height: 16),
                TextFormField(
                  controller: countryController,
                  decoration: InputDecoration(labelText: t.countryLabel),
                ),
                const SizedBox(height: 16),
                TextFormField(
                  controller: postalController,
                  decoration: InputDecoration(labelText: t.postalCodeLabel),
                  keyboardType: TextInputType.streetAddress,
                ),
                const SizedBox(height: 24),
                GFButton(
                  onPressed: () async {
                    final navigator = Navigator.of(ctx);
                    final phone = phoneController.text.trim();
                    final address = addressController.text.trim();
                    final city = cityController.text.trim();
                    final country = countryController.text.trim();
                    final postalCode = postalController.text.trim();
                    final res = await ApiService.instance.updateCustomerProfile(
                      address: address.isEmpty ? null : address,
                      city: city.isEmpty ? null : city,
                      country: country.isEmpty ? null : country,
                      postalCode: postalCode.isEmpty ? null : postalCode,
                      phone: phone.isEmpty ? null : phone,
                    );
                    if (!ctx.mounted) return;
                    if (res.success) {
                      await profile.save(
                        phone: phone.isEmpty ? null : phone,
                        address: address.isEmpty ? null : address,
                        city: city.isEmpty ? null : city,
                        country: country.isEmpty ? null : country,
                        postalCode: postalCode.isEmpty ? null : postalCode,
                      );
                      if (!ctx.mounted) return;
                      navigator.pop();
                    }
                  },
                  text: t.save,
                  fullWidthButton: true,
                  color: Theme.of(ctx).colorScheme.primary,
                ),
              ],
            ),
          ),
        );
      },
    );
  }
}
