provider "aws" {
    region = "us-east-1"
    profile = "cosc349"
}

variable "repo_url" {
    description = "Public Git URL of this repository"
    default     = "https://github.com/cdavidsotto/recipe-finder.git"
}

variable "db_host" {
    description = "RDS endpoint, created manually in the console"
    default     = "recipefinder-db.cum548yzqlxl.us-east-1.rds.amazonaws.com"
}

variable "db_password" {
    description = "RDS admin password (typed at apply time, never committed)"
    sensitive   = true
}

resource "aws_security_group" "allow_ssh" {
    name        = "allow_ssh"
    description = "Allow inbound SSH traffic"

    ingress {
        description = "SSH from anywhere"
        from_port   = 22
        to_port     = 22
        protocol    = "tcp"
        cidr_blocks = ["0.0.0.0/0"]
    }

    egress {
        from_port   = 0
        to_port     = 0
        protocol    = "-1"
        cidr_blocks = ["0.0.0.0/0"]
    }
}

resource "aws_instance" "web_server" {
    ami           = "ami-0360c520857e3138f"
    instance_type = "t2.micro"
    key_name      = "vockey"

    vpc_security_group_ids = [aws_security_group.allow_ssh.id, aws_security_group.allow_web.id]

    user_data = templatefile("${path.module}/build-webserver-vm.tpl", {
        app_private_ip = aws_instance.app_server.private_ip
    })

    tags = {
        Name = "WebServer"
    }
}

resource "aws_security_group" "allow_web" {
    name        = "allow_web"
    description = "Allow inbound web traffic"

    ingress {
        description = "HTTP from anywhere"
        from_port   = 80
        to_port     = 80
        protocol    = "tcp"
        cidr_blocks = ["0.0.0.0/0"]
    }

    ingress {
        description = "HTTPS from anywhere"
        from_port   = 443
        to_port     = 443
        protocol    = "tcp"
        cidr_blocks = ["0.0.0.0/0"]
    }

    egress {
        from_port   = 0
        to_port     = 0
        protocol    = "-1"
        cidr_blocks = ["0.0.0.0/0"]
    }
}

resource "aws_instance" "app_server" {
    ami           = "ami-0360c520857e3138f"
    instance_type = "t2.micro"
    key_name      = "vockey"

    vpc_security_group_ids = [aws_security_group.allow_ssh.id, aws_security_group.allow_web.id]

    iam_instance_profile = "LabInstanceProfile"

    user_data = templatefile("${path.module}/build-appserver-vm.tpl", {
        repo_url    = var.repo_url
        db_host     = var.db_host
        db_password = var.db_password
    })

    tags = {
        Name = "AppServer"
    }
}

resource "aws_security_group" "allow_mysql" {
    name        = "allow_mysql"
    description = "Allow inbound MySQL traffic"

    ingress {
        description = "MySQL from anywhere"
        from_port   = 3306
        to_port     = 3306
        protocol    = "tcp"
        cidr_blocks = ["0.0.0.0/0"]
    }

    egress {
        from_port   = 0
        to_port     = 0
        protocol    = "-1"
        cidr_blocks = ["0.0.0.0/0"]
    }
}

output "web_server_ip" {
    value = aws_instance.web_server.public_ip
}

output "app_server_ip" {
    value = aws_instance.app_server.public_ip
}
