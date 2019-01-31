FROM ruby:2.4

# throw errors if Gemfile has been modified since Gemfile.lock
RUN bundle config --global frozen 1

WORKDIR /usr/src/app

COPY Gemfile Gemfile.lock ./
RUN bundle install

COPY . .

# CMD ["tail", "-f", "/dev/null"]

# FROM ruby

# RUN gem update --system && \
#     gem install sequel mysql2 fileutils safe_yaml jekyll-import
