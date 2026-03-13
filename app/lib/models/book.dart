import '../config.dart';

class Book {
  Book({
    required this.id,
    required this.title,
    required this.price,
    this.stockQuantity = 0,
    this.isbn,
    this.category,
    this.authors,
    this.description,
    this.pages,
    this.publishYear,
    this.publisher,
    this.size,
    this.weight,
    this.coverImage,
    this.coverImageThumb,
    this.editionNumber,
    this.discountPercent,
  });

  final String id;
  final String title;
  final double price;
  final int stockQuantity;
  final String? isbn;
  final Category? category;
  final List<Author>? authors;
  final String? description;
  final int? pages;
  final int? publishYear;
  final String? publisher;
  final String? size;
  final double? weight;
  final String? coverImage;
  final String? coverImageThumb;
  final int? editionNumber;
  final int? discountPercent;

  /// True when the book has at least one cover image URL (thumb or full).
  bool get hasCover {
    final c = (coverImageThumb ?? coverImage)?.trim();
    if (c == null || c.isEmpty) return false;
    final lowered = c.toLowerCase();
    return lowered != "null" && lowered != "undefined";
  }

  static String? _fixUrl(String? url) {
    if (url == null || url.isEmpty) return null;
    if (url.startsWith('http://localhost:8000') || url.startsWith('http://127.0.0.1:8000')) {
      final base = Uri.parse(apiBaseUrl);
      return url.replaceFirst('localhost', base.host).replaceFirst('127.0.0.1', base.host);
    }
    return url;
  }

  factory Book.fromJson(Map<String, dynamic> json) {
    return Book(
      id: json['_id'] ?? json['id'] ?? '',
      title: json['title'] ?? '',
      price: (json['price'] as num?)?.toDouble() ?? 0.0,
      stockQuantity: json['stock_quantity'] ?? 0,
      isbn: json['isbn'],
      category: json['category'] != null ? Category.fromJson(json['category']) : null,
      authors: (json['authors'] as List?)?.map((e) => Author.fromJson(e)).toList(),
      description: json['description'],
      pages: json['pages'],
      publishYear: json['publish_year'],
      publisher: json['publisher'],
      size: json['size'],
      weight: (json['weight'] as num?)?.toDouble(),
      coverImage: _fixUrl(json['cover_image']),
      coverImageThumb: _fixUrl(json['cover_image_thumb']),
      editionNumber: json['edition_number'],
      discountPercent: json['discount_percent'],
    );
  }

  Map<String, dynamic> toJson() {
    return {
      '_id': id,
      'title': title,
      'price': price,
      'stock_quantity': stockQuantity,
      'isbn': isbn,
      'author_ids': authors?.map((a) => a.id).toList(),
      'category_id': category?.id,
      'description': description,
      'pages': pages,
      'publish_year': publishYear,
      'publisher': publisher,
      'size': size,
      'weight': weight,
      'cover_image': coverImage,
      'cover_image_thumb': coverImageThumb,
      'edition_number': editionNumber,
      'discount_percent': discountPercent,
    };
  }
}


class Category {
  Category({required this.id, this.deweyCode, this.subjectTitle});

  final String id;
  final String? deweyCode;
  final String? subjectTitle;

  factory Category.fromJson(Map<String, dynamic> json) {
    final id = json['_id'] ?? json['id'] ?? '';
    return Category(
      id: id.toString(),
      deweyCode: json['dewey_code'],
      subjectTitle: json['subject_title'],
    );
  }
}

class Author {
  Author({required this.id, this.name});

  final String id;
  final String? name;

  factory Author.fromJson(Map<String, dynamic> json) {
    final id = json['_id'] ?? json['id'] ?? '';
    return Author(id: id.toString(), name: json['name']);
  }
}
