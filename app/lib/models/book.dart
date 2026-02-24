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

  factory Book.fromJson(Map<String, dynamic> json) {
    final id = json['_id'] ?? json['id'] ?? '';
    return Book(
      id: id.toString(),
      title: json['title'] ?? '',
      price: (json['price'] ?? 0).toDouble(),
      stockQuantity: json['stock_quantity'] ?? 0,
      isbn: json['isbn'],
      category: json['category'] != null
          ? Category.fromJson(json['category'] as Map<String, dynamic>)
          : null,
      authors: json['authors'] != null
          ? (json['authors'] as List)
              .map((a) => Author.fromJson(a as Map<String, dynamic>))
              .toList()
          : null,
      description: json['description'],
      pages: json['pages'],
      publishYear: json['publish_year'],
      publisher: json['publisher'],
      size: json['size'],
      weight: (json['weight'] as num?)?.toDouble(),
      coverImage: json['cover_image'],
      coverImageThumb: json['cover_image_thumb'],
      editionNumber: json['edition_number'],
    );
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
